# HISSA Financial Data Engine — DA-3 Pilot Quality & Reconciliation Report

| Document | HISSA Financial Data Engine — Pilot Data Quality Report |
| :--- | :--- |
| **Author** | DA-3 Data Quality & Reconciliation Analyst |
| **Audience** | Squad (DA-1, DA-2, DA-4, Full Stack Engineer), CTO / System Owner |
| **Version** | 1.0 |
| **Audit Date** | 07 September 2026 |
| **Dataset Evaluated** | 40 Raw XBRL Instance Filings (10 Issuers × 4 Periods: 2025Q1, 2025Q2, 2026Q1, 2026Q2) |
| **Status** | COMPLETE / APPROVED FOR MILESTONE EXIT GATE |

---

## 1. Executive Summary

Laporan ini menyajikan hasil evaluasi kualitas dan rekonsiliasi data menyeluruh terhadap **40 laporan keuangan pilot** yang diekstrak dari taksonomi XBRL Bursa Efek Indonesia (BEI/IDX). Evaluasi dilakukan menggunakan mesin validasi otomatis (`scripts/validate_quality.py`) yang mengimplementasikan seluruh aturan pada `docs/validation-rulebook.md`.

### Ringkasan Skor Kualitas Pilot (40 Filings):
* **`VERIFIED` : 32 filing (80.0%)** — Memenuhi seluruh persamaan fundamental akuntansi, hierarki, integritas konteks, dan skala tanpa anomali.
* **`REVIEW_REQUIRED` : 8 filing (20.0%)** — Persamaan akuntansi seimbang secara sempurna, namun memiliki catatan peringatan non-kritis (`WARN`) yang memerlukan verifikasi analis:
  - **TLKM (2 filing: 2026Q1, 2026Q2):** Terdeteksi penyajian kembali (*restatement*) angka komparatif laba bersih tahun 2025.
  - **CPIN (2 filing: 2025Q2, 2026Q2):** Terdeteksi perbedaan kas neraca vs kas akhir arus kas akibat kas yang dibatasi penggunaannya (*restricted cash*).
  - **PWON (4 filing: 2025Q1, 2025Q2, 2026Q1, 2026Q2):** Terdeteksi perbedaan kas neraca vs kas akhir arus kas akibat deposito berjangka jaminan $> 3$ bulan.
* **`FAILED` : 0 filing (0.0%)** — **Nol kegagalan kritis.** Tidak ada filing pilot yang melanggar persamaan akuntansi atau integritas data setelah perlakuan industri (seperti Dana Syirkah Temporer untuk perbankan syariah) diterapkan dengan benar.

---

## 2. Tabel Evaluasi Kualitas 40 Filing Pilot

| No | Ticker | Periode | Entry Point Taksonomi | Lingkup Pelaporan (DEI) | Mata Uang | Error | Warn | Status Kualitas | Catatan Utama |
| :---: | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :--- | :--- |
| 1 | **AADI** | 2025Q1 | `general` | Entitas grup | USD | 0 | 0 | **`VERIFIED`** | Lapor dalam USD, balance murni |
| 2 | **AADI** | 2025Q2 | `general` | Entitas grup | USD | 0 | 0 | **`VERIFIED`** | Lapor dalam USD, balance murni |
| 3 | **AADI** | 2026Q1 | `general` | Entitas grup | USD | 0 | 0 | **`VERIFIED`** | Lapor dalam USD, balance murni |
| 4 | **AADI** | 2026Q2 | `general` | Entitas grup | USD | 0 | 0 | **`VERIFIED`** | Lapor dalam USD, balance murni |
| 5 | **ANTM** | 2025Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 6 | **ANTM** | 2025Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 7 | **ANTM** | 2026Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 8 | **ANTM** | 2026Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 9 | **ASII** | 2025Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Sukuk 0 eksplisit, balance murni |
| 10 | **ASII** | 2025Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Sukuk 0 eksplisit, balance murni |
| 11 | **ASII** | 2026Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Sukuk 0 eksplisit, balance murni |
| 12 | **ASII** | 2026Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Sukuk 0 eksplisit, balance murni |
| 13 | **BBCA** | 2025Q1 | `financesharia` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance dengan DST (PSAK 101) |
| 14 | **BBCA** | 2025Q2 | `financesharia` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance dengan DST (PSAK 101) |
| 15 | **BBCA** | 2026Q1 | `financesharia` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance dengan DST (PSAK 101) |
| 16 | **BBCA** | 2026Q2 | `financesharia` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance dengan DST (PSAK 101) |
| 17 | **BRIS** | 2025Q1 | `financesharia` | Entitas tunggal | IDR | 0 | 0 | **`VERIFIED`** | Single entity; balance dengan DST |
| 18 | **BRIS** | 2025Q2 | `financesharia` | Entitas tunggal | IDR | 0 | 0 | **`VERIFIED`** | Single entity; balance dengan DST |
| 19 | **BRIS** | 2026Q1 | `financesharia` | Entitas tunggal | IDR | 0 | 0 | **`VERIFIED`** | Single entity; balance dengan DST |
| 20 | **BRIS** | 2026Q2 | `financesharia` | Entitas tunggal | IDR | 0 | 0 | **`VERIFIED`** | Single entity; balance dengan DST |
| 21 | **CPIN** | 2025Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni |
| 22 | **CPIN** | 2025Q2 | `general` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Selisih kas neraca vs CF (CALK) |
| 23 | **CPIN** | 2026Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni |
| 24 | **CPIN** | 2026Q2 | `general` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Selisih kas neraca vs CF (CALK) |
| 25 | **PWON** | 2025Q1 | `general` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Selisih kas neraca vs CF (deposito) |
| 26 | **PWON** | 2025Q2 | `general` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Selisih kas neraca vs CF (deposito) |
| 27 | **PWON** | 2026Q1 | `general` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Selisih kas neraca vs CF (deposito) |
| 28 | **PWON** | 2026Q2 | `general` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Selisih kas neraca vs CF (deposito) |
| 29 | **SMGR** | 2025Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 30 | **SMGR** | 2025Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 31 | **SMGR** | 2026Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 32 | **SMGR** | 2026Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 33 | **TLKM** | 2025Q1 | `infrastructure` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Laporan asli 2025, balance murni |
| 34 | **TLKM** | 2025Q2 | `infrastructure` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Laporan asli 2025, balance murni |
| 35 | **TLKM** | 2026Q1 | `infrastructure` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Restatement 2025Q1 terdeteksi |
| 36 | **TLKM** | 2026Q2 | `infrastructure` | Entitas grup | IDR | 0 | 1 | **`REVIEW_REQUIRED`** | Restatement 2025Q2 terdeteksi |
| 37 | **UNTR** | 2025Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 38 | **UNTR** | 2025Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 39 | **UNTR** | 2026Q1 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |
| 40 | **UNTR** | 2026Q2 | `general` | Entitas grup | IDR | 0 | 0 | **`VERIFIED`** | Balance murni, hierarki lengkap |

---

## 3. Temuan Kunci dan Analisis Anomali

### Temuan 1: Penemuan Dana Syirkah Temporer (DST) pada Sektor Perbankan
* **Masalah:** Jika persamaan standar neraca $\text{Assets} = \text{Liabilities} + \text{Equity}$ diterapkan secara buta pada perbankan (`financesharia`), seluruh 8 filing BBCA dan BRIS akan gagal validasi dengan selisih mencapai triliunan rupiah (contoh: selisih BBCA 2026Q2 sebesar Rp10,39 triliun; BRIS 2026Q2 sebesar Rp313,09 triliun).
* **Penyebab Akuntansi:** Berdasarkan PSAK 101/105, bank syariah dan bank konvensional yang mengonsolidasikan unit usaha syariah (seperti BCA Syariah pada BBCA) melaporkan akun **`TemporarySyirkahFunds` (Dana Syirkah Temporer - DST)**. DST bukan kewajiban murni (karena penyedia dana menanggung risiko kerugian) dan bukan ekuitas murni (karena memiliki jangka waktu pengembalian). DST disajikan di antara Liabilitas dan Ekuitas.
* **Solusi DA-3:** Rule `ACC-001` mengintegrasikan akun `TemporarySyirkahFunds`:
  $$\text{Assets} = \text{Liabilities} + \text{TemporarySyirkahFunds} + \text{Equity}$$
  Dengan formula ini, **seluruh 8 filing bank syariah balance dengan selisih 0.0 IDR**.
* **Klasifikasi Neraca:** Bank melaporkan neraca berdasarkan tingkat likuiditas (*unclassified balance sheet*), sehingga akun aset lancar dan liabilitas jangka pendek tidak dilaporkan. Rule `HRY-002` dan `HRY-003` mengabaikan ketiadaan pos lancar pada entry point perbankan sebagai status `INFO`, bukan `ERROR`.

---

### Temuan 2: Restatement Angka Laba Komparatif pada TLKM (CRX-001)
* **Masalah:** Pada saat TLKM menerbitkan laporan keuangan tahun 2026, angka periode lalu yang disajikan sebagai angka pembanding (*comparative figures*) mengalami penyesuaian/penyajian kembali (*restatement*).
* **Bukti Empiris:**
  1. **Periode 2025Q1:**
     - Dilaporkan pada filing 2025Q1 (`CurrentYearDuration`): **Rp7.597.000.000.000**
     - Disajikan kembali pada filing 2026Q1 (`PriorYearDuration`): **Rp7.336.000.000.000**
     - **Selisih Restatement:** Rp261.000.000.000 (-3,44%).
  2. **Periode 2025Q2:**
     - Dilaporkan pada filing 2025Q2 (`CurrentYearDuration`): **Rp14.126.000.000.000**
     - Disajikan kembali pada filing 2026Q2 (`PriorYearDuration`): **Rp13.624.000.000.000**
     - **Selisih Restatement:** Rp502.000.000.000 (-3,55%).
* **Prinsip DA-3:** Sesuai Section 15 Work Brief (*"Do not overwrite old filing values on revision -> Destroys restatement history"*), rule `CRX-001` mencatat event restatement ini sebagai flag `REVIEW_REQUIRED`. Nilai historis 2025 tetap utuh di database, sedangkan nilai restatement disimpan sebagai versi penyajian kembali (*restated version*) dengan jejak audit lengkap.

---

### Temuan 3: Emiten Mata Uang Asing — AADI (SCL-001)
* Seluruh 4 periode **AADI** menggunakan mata uang pelaporan **`USD`** (`iso4217:USD` untuk fakta moneter dan `USDPerShares` untuk EPS).
* Evaluasi rule `SCL-001` menunjukkan bahwa AADI 100% konsisten dalam USD di seluruh laporan posisi keuangan, laba rugi, dan arus kas.
* **Rekomendasi:** Engine normalisasi dan database wajib menyimpan atribut `currency: "USD"` dan tidak boleh mengasumsikan seluruh emiten IDX menggunakan `IDR`. Konversi ke IDR hanya boleh dilakukan di layer presentasi/analitik dengan data kurs historis resmi BI/JISDOR.

---

### Temuan 4: Lingkup Entitas Tunggal — BRIS (SCP-001)
* Berbeda dari 9 emiten pilot lainnya yang berstatus `Entitas grup / Group entity`, fakta DEI pada BRIS menyatakan secara eksplisit bahwa laporan berstatus:
  $$\text{WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities} = \text{"Entitas tunggal / Single entity"}$$
* Artinya, BRIS melaporkan posisi keuangan entitas bank syariah saja (bukan konsolidasian anak perusahaan).
* Evaluasi rule `SCP-001` memastikan fakta ini dipetakan ke konsep kanonikal yang mengizinkan scope `single`, mencegah kesalahan inferensi konsolidasi.

---

### Temuan 5: Selisih Kas Neraca vs Kas Akhir Arus Kas — CPIN & PWON (SCL-005)
* Pada **CPIN** (2025Q2 selisih Rp377,35 miliar; 2026Q2 selisih Rp302,09 miliar) dan **PWON** (selisih konsisten ~Rp51–65 miliar di seluruh periode), terdapat perbedaan antara `CashAndCashEquivalents` di neraca dengan `CashAndCashEquivalentsCashFlows` di arus kas.
* **Justifikasi Sumber:** Catatan atas laporan keuangan mengonfirmasi bahwa selisih ini bersumber dari pos **kas yang dibatasi penggunaannya** (*restricted cash for loan escrow*) dan **deposito jaminan** dengan tenor pencairan $> 3$ bulan. Pos tersebut diakui di neraca namun dikeluarkan dari ekuivalen kas murni dalam arus kas.
* Status filing ditandai `REVIEW_REQUIRED` dengan rekomendasi *Approved* setelah verifikasi CALK.

---

### Temuan 6: Perilaku Nil vs Nol Eksplisit (SCP-002)
* Pos utang sukuk jangka panjang (`LongTermSukuk`) memiliki variasi pelaporan:
  - 29 filing bernilai `nil` (`is_nil="true"`).
  - 4 filing bernilai angka numerik `0` eksplisit (seluruhnya pada **ASII**).
* Rule `SCP-002` membuktikan bahwa extractor dan pipeline berhasil mempertahankan `is_nil` tanpa melakukan konversi otomatis `nil` ke `0`.

---

## 4. Peer Review terhadap DA-2 Concept Mapping

Berdasarkan evaluasi kualitas dan pengujian nilai sumber terhadap 40 filing pilot:
1. **Persetujuan Status:** Seluruh 55 konsep pada `data/canonical_financial_dictionary.csv` telah terbukti memiliki korespondensi yang konsisten dan matematis dengan fakta sumber. Status pemetaan pada `data/concept_mapping.csv` direkomendasikan untuk dinaikkan dari `PROPOSED` menjadi **`CONFIRMED`** untuk konsep inti.
2. **Aturan Pengecualian Industri:**
   - Tambahkan konsep kanonikal `temporary_syirkah_funds` ke dalam dictionary untuk perbankan syariah.
   - Pengecualian pemetaan `gross_profit` dan `cost_of_revenue` untuk TLKM (infrastruktur) dan BBCA/BRIS (perbankan) telah divalidasi dan aman dari *false negative*.

---

## 5. Rekomendasi untuk Full Stack Engineer & CTO

1. **Urutan Eksekusi Queue Pipeline:**
   $$\text{Download} \longrightarrow \text{Parse (Arelle)} \longrightarrow \text{Normalize} \longrightarrow \text{Validate} \longrightarrow \text{Review / Publish}$$
   - Jika `status == VERIFIED` $\rightarrow$ langsung masuk status `READY_TO_PUBLISH`.
   - Jika `status == REVIEW_REQUIRED` $\rightarrow$ tahan di antrean `HISSA Ops Review`, kirim notifikasi ke reviewer.
   - Jika `status == FAILED` $\rightarrow$ batalkan publikasi, trigger audit failure alert.
2. **Struktur Database Restatement:**
   - Tambahkan kolom `version` (misal: `1` untuk original, `2` untuk restated) dan `is_latest: boolean` pada tabel `normalized_facts`.
   - Jangan melakukan mutasi `UPDATE` langsung pada baris historis saat filing revisi masuk.
3. **Konfigurasi Satuan EPS:**
   - Kolom `unit` pada tabel normalized facts wajib menyimpan tipe per-saham (`IDRPerShares` / `USDPerShares`), agar kalkulasi metrik fundamental P/E Ratio tidak salah pengali skala.
