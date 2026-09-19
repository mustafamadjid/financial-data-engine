# DA-2 Aturan Pemetaan

## Lingkup

Pemetaan mengidentifikasi suatu fakta sumber; pemetaan tidak menimpa `raw_facts.csv` atau menciptakan nilai yang hilang. Pilot pemetaan mencakup 40 laporan: 28 `general`, 8 `financesharia`, dan 4 `infrastructure`. Inventori ruang nama target disediakan oleh DA-1 karena `filings.csv` hanya menyimpan referensi `Taxonomy.xsd` lokal. `concept_mapping.csv` mencatat ketiga ruang nama titik masuk target untuk pemetaan kandidat; ketersediaannya tetap harus diperiksa terhadap fakta sumber setiap laporan.

| Ruang nama target titik masuk | Cakupan laporan | Penerbit pilot |
| --- | ---: | --- |
| `.../ep/E24/general` | 28 | AADI, ANTM, ASII, CPIN, PWON, SMGR, UNTR |
| `.../ep/E24/financesharia` | 8 | BBCA, BRIS |
| `.../ep/E24/infrastructure` | 4 | TLKM |

## Identitas dan konteks

1. Cocokkan `source_concept` bersama dengan `concept_namespace`; nama lokal saja tidak stabil antar taksonomi.
2. Untuk konsep neraca, gunakan `period_type=instant`, biasanya `CurrentYearInstant` untuk tanggal saat ini dan `PriorEndYearInstant` untuk perbandingan akhir tahun sebelumnya.
3. Untuk konsep laporan laba rugi dan arus kas, gunakan `period_type=duration`, biasanya `CurrentYearDuration` dan `PriorYearDuration`.
4. Durasi Q2 adalah tahun berjalan (year-to-date, 1 Januari s.d. 30 Juni), bukan hanya Q2 saja. Jangan kurangi periode kecuali ada aturan tervalidasi yang disetujui.
5. Fakta laporan default harus tidak memiliki dimensi. Fakta yang `context_id`-nya diakhiri dengan sebuah anggota adalah fakta rinci dan dikeluarkan dari total default.
6. Pecahkan detail konteks melalui `contexts.csv` dan `dimensions.csv`; jangan pernah menyimpulkan makna lengkap hanya dari string context ID.

## Lingkup dan konsolidasi

1. Baca `WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities` dari fakta DEI.
2. `Group entity` mengizinkan pemetaan terkonsolidasi; `Single entity` mengizinkan pemetaan hanya induk. Keduanya tidak dapat dipertukarkan.
3. Lingkup yang hilang atau tidak dikenal adalah `REVIEW_REQUIRED`.
4. Nama titik masuk, termasuk `financesharia`, adalah metadata teknis dan bukan merupakan bukti klasifikasi bisnis atau syariah.
5. Suatu pemetaan berlaku untuk suatu titik masuk hanya jika konsep sumber dan ruang nama yang persis sama tersedia dalam laporan tersebut; ketiadaan adalah `NOT_REPORTED`, bukan nilai nol.

## Nilai, tanda, dan satuan

1. Pertahankan `value_raw`, `unit_id`, `decimals`, `is_nil`, dan pengidentifikasi sumber.
2. `is_nil=true` berarti tidak tersedia / tidak dilaporkan dan tidak boleh diubah menjadi nol.
3. Nilai nol numerik eksplisit adalah nol yang dilaporkan secara sah.
4. Konsep kanonikal menggunakan tanda sumber secara default. Tidak ada konversi nilai absolut yang diizinkan.
5. `decimals` menggambarkan presisi pelaporan; ini bukan faktor skala. Satuan penyajian (misalnya, jutaan IDR) harus dikonfirmasi dari DEI sebelum normalisasi tampilan.
6. Pemetaan EPS memerlukan satuan per saham seperti `IDRPerShares`, bukan satuan mata uang.

## Status dan peninjauan

- `PROPOSED`: pemetaan semantik didukung oleh nama sumber dan bukti pilot, tetapi menunggu peninjauan sejawat DA-1/DA-3.
- `CONFIRMED`: peninjauan sejawat dan uji nilai sumber telah lulus.
- `REVIEW_REQUIRED`: lingkup, dimensi, satuan, perilaku nil, atau keterbandingan semantik belum terselesaikan.
- `REJECTED`: konsep sumber tidak dapat dibandingkan dengan definisi kanonikal.

## Pengecualian yang diketahui

- `CashAndCashEquivalents` (neraca, instant) dan `CashAndCashEquivalentsCashFlows` (rekonsiliasi arus kas, duration) adalah konsep terpisah.
- Pinjaman jangka pendek dan jangka panjang tidak boleh dijumlahkan secara membabi buta: konsep jatuh tempo dapat tumpang tindih dengan konsep pinjaman. Tentukan aturan komposisi utang sebelum menurunkan total utang.
- `LongTermSukuk` dan konsep sukuk terkait dapat bernilai `nil` atau nol eksplisit. `nil` tetap `REVIEW_REQUIRED` untuk metrik utang/terutang.
- Konteks rinci seperti `..._CommonStocksMember` berguna untuk bukti rekonsiliasi ekuitas, tetapi bukan merupakan fakta total ekuitas default.
- `Other*`, blok teks, dan komoditas spesifik industri tidak dipetakan ke total keuangan generik kecuali definisi dan aturan lingkup disetujui.
- Konsep sumber dengan nama mirip bahasa Inggris di seluruh `cor`, `dei`, dan `rt` adalah berbeda sampai ruang nama dan definisi disepakati.

## Kontrak implementasi untuk teknisi

Konsumen (pemroses) harus menggabungkan `concept_mapping.csv` dengan fakta mentah berdasarkan `source_concept` dan `concept_namespace`, memfilter ke titik masuk laporan dan inventori ticker, lalu menerapkan aturan pernyataan, jenis periode, lingkup, satuan, nil, dan dimensi dari baris kanonikal. Keluaran yang dinormalisasi harus mempertahankan `ticker`, `period`, `canonical_concept`, `value`, `currency`, lingkup eksplisit, `data_type`, `source_concept`, `filing_id`, dan `validation_status`. Kolom yang tidak diketahui menggunakan null atau status eksplisit; kolom tersebut tidak boleh diciptakan. `source_element_id` asli dan hash laporan tetap menjadi jejak audit.
