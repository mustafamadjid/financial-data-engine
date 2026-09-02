# IDX Taxonomy Notes — DA-1 Pilot Dataset

Tanggal inventaris: 1 September 2026  
Sumber bukti: `Taxonomy.xsd` di dalam 40 `instance.zip` pilot.  
Dataset terstruktur: `data/extracted/taxonomy_inventory.csv`.

## Ringkasan

Seluruh filing pilot memiliki `Taxonomy.xsd` dan menggunakan taxonomy IDX bertanggal `2020-01-01`. Tiga entry point ditemukan pada pilot:

| Entry point target namespace | Jumlah filing | Issuer pilot |
| --- | ---: | --- |
| `.../ep/E24/general` | 28 | AADI, ANTM, ASII, CPIN, PWON, SMGR, UNTR |
| `.../ep/E24/financesharia` | 8 | BBCA, BRIS |
| `.../ep/E24/infrastructure` | 4 | TLKM |

Nama entry point adalah klasifikasi teknis taxonomy, bukan bukti klasifikasi bisnis atau syariah. Contohnya, BBCA dan BRIS sama-sama menggunakan entry point `financesharia`; karena itu status syariah tidak boleh disimpulkan dari entry point.

## Struktur filing yang ditemukan

Setiap arsip pilot berisi dua artefak:

```text
instance.xbrl    # facts, contexts, units, dan source references
Taxonomy.xsd     # entry point lokal, imports, dan linkbase references
```

`Taxonomy.xsd` pada seluruh 40 filing mengimpor tiga namespace IDX berikut:

```text
http://www.idx.co.id/xbrl/taxonomy/2020-01-01/dei
http://www.idx.co.id/xbrl/taxonomy/2020-01-01/cor
http://www.idx.co.id/xbrl/taxonomy/2020-01-01/rt
```

Lokasi schema import adalah URL IDX. Dataset pilot hanya menyimpan entry point lokal dan referensinya; resolver/parser production perlu mengelola akses dan caching taxonomy remote secara eksplisit.

## Linkbase dan statement families

Semua filing mereferensikan linkbase untuk lima family berikut:

| Kode pada referensi | Family | Peran untuk pipeline |
| --- | --- | --- |
| `BS` | Balance sheet | Membantu menemukan struktur laporan posisi keuangan. |
| `PL` | Income statement | Membantu menemukan struktur laba rugi. |
| `CE` | Changes in equity | Struktur perubahan ekuitas. |
| `CF` | Cash flow | Struktur arus kas. |
| `NT` | Notes | Catatan/disclosure; penting untuk debt, creditor, bonds, dan sukuk. |

Role linkbase yang ditemukan mencakup presentation, calculation, dan definition. Jumlah referensi per filing tidak tetap: **51–97 linkbase**. Karena itu pipeline tidak boleh mengasumsikan daftar atau jumlah linkbase yang fixed untuk semua issuer/periode.

## Context dan konvensi periode

Seluruh 40 filing mempunyai tujuh context utama tanpa dimensi dengan ID yang konsisten. Context lain adalah detail disclosure berdimensi dan tidak menjadi kandidat default untuk nilai total statement.

| Context ID | Tipe | Konvensi penggunaan |
| --- | --- | --- |
| `CurrentYearInstant` | instant | Balance sheet pada tanggal akhir periode kini. |
| `PriorEndYearInstant` | instant | Balance sheet pada akhir tahun sebelumnya. |
| `CurrentYearDuration` | duration | Income statement/cash flow year-to-date: 1 Januari hingga tanggal akhir periode kini. |
| `PriorYearDuration` | duration | Pembanding year-to-date pada tahun sebelumnya. |
| `PriorYearInstant` | instant | Pembanding balance sheet pada tanggal ekuivalen tahun sebelumnya. |
| `Prior2YearsInstant` | instant | Context historis; bukan kandidat default periode kini. |
| `PriorEndYearDuration` | duration | Tahun penuh sebelumnya; bukan kandidat default laporan kuartalan. |

Contoh Q2: `CurrentYearDuration` adalah 1 Januari–30 Juni, bukan nilai Q2-only. Nilai kuartal tunggal hanya boleh diturunkan dengan aturan yang tervalidasi. Scope filing tidak diambil dari nama context; gunakan fact DEI `WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities` bila tersedia.

## Units dan decimals

Setiap filing pilot memiliki dua unit XBRL:

| Unit | Coverage | Struktur |
| --- | ---: | --- |
| Mata uang | 36 filing IDR; 4 filing USD (seluruhnya AADI) | `iso4217:IDR` atau `iso4217:USD` |
| Per saham | 40 filing | Unit divide: mata uang / `shares` (`IDRPerShares` atau `USDPerShares`) |

Decimals yang paling sering pada numeric fact adalah kosong/tidak disediakan, `-6`, `-9`, `-3`, dan `INF`. `decimals` adalah metadata ketelitian/pembulatan, bukan faktor yang boleh diterapkan ulang ke `value_raw`. Raw value, unit, dan decimals harus disimpan bersama. Normalisasi skala dan tolerance rekonsiliasi adalah keputusan DA-2/DA-3.

## Useful note nodes untuk handoff debt/financing

Node berikut ditemukan dalam inventory source concept dan harus dipertahankan sebagai kandidat evidence, bukan langsung dimapping menjadi nilai final:

| Area | Source concept / note node | Coverage filing |
| --- | --- | ---: |
| Borrowings note | `BorrowingsTextBlock` | 40/40 |
| Short-term bank loans note | `DisclosureofNotesforShortTermBankLoansTextBlock` | 32/40 |
| Long-term bank loans note | `DisclosureofNotesforLongTermBankLoansTextBlock` | 32/40 |
| Bank loans | `BankLoans`, `CurrentMaturitiesOfBankLoans` | 32/40 |
| Bonds | `LongTermBondsPayable` | 33/40 |
| Sukuk | `LongTermSukuk`, `CurrentMaturitiesOfSukuk` | 33/40 |
| Interest/finance | `InterestAndFinanceCosts`, `InterestIncome` | 32/40 dan 36/40 |

Debt/currency/creditor/maturity/facility detail umumnya berada di context berdimensi. Simpan axis/member dan source evidence; jangan menjumlahkan detail sebagai total tanpa rule khusus. Concept `nil` juga harus dibedakan dari nilai nol.

## Catatan teknis untuk extractor dan pipeline

1. `instance.xbrl` adalah sumber raw fact; `Taxonomy.xsd` menyediakan entry point dan relasi ke taxonomy/linkbase.
2. Simpan `target_namespace`, import namespace/location, dan seluruh linkbase reference per filing. Informasi ini sudah tersedia pada `taxonomy_inventory.csv`.
3. Gunakan namespace + local concept name sebagai identitas source concept; local name saja tidak cukup untuk skala lintas taxonomy.
4. Linkbase `NT` harus dipertahankan dalam inventory karena note disclosure adalah sumber utama debt intelligence. Nilai lender/facility tidak boleh diasumsikan tersedia pada statement utama.
5. Arelle atau resolver taxonomy production harus menggunakan cache dan error logging. Jangan bergantung pada URL remote secara diam-diam saat proses normalisasi.

## Keterbatasan dan keputusan yang belum dibuat

- Inventory source concept dan note node penting tersedia pada `docs/key-concept-inventory.md` dan `data/extracted/key_concept_inventory.csv`. Inventory tersebut belum merupakan semantic mapping; keputusan canonical mapping tetap pekerjaan DA-2 dan harus melalui bukti filing.
- Belum ada klasifikasi consolidated vs parent dari entry point; scope tetap membutuhkan bukti tambahan dari filing/taxonomy metadata.
- Belum ada keputusan lisensi atau strategi cache/redistribusi taxonomy remote; eskalasi ke CTO/business/legal sesuai `project.md` bila diperlukan.

## Menjalankan ulang inventory

```powershell
python scripts/profile_taxonomies.py
```

Perintah ini membaca `data/raw/<TICKER>/<PERIODE>/instance.zip` dan memperbarui `data/extracted/taxonomy_inventory.csv`.
