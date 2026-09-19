# HISSA Financial Data Engine — DA-3 Manual Source Reconciliation Checklist

| Document | HISSA Financial Data Engine — Standard Operating Procedure & Reconciliation Checklist |
| :--- | :--- |
| **Owner** | DA-3 Data Quality & Reconciliation Analyst |
| **Audience** | Data Analysts (DA-1, DA-2, DA-3, DA-4), HISSA Ops Reviewers, CTO |
| **Version** | 1.0 |
| **Date** | 07 September 2026 |
| **Status** | PRODUCTION STANDARD |

---

## 1. Tujuan & Prinsip Rekonsiliasi Sumber

Checklist ini adalah panduan standar bagi Data Analyst dan HISSA Ops Reviewer untuk melakukan verifikasi silang (*tie-out*) antara fakta kanonikal yang dihasilkan pipeline ekstraksi dengan dokumen sumber resmi yang dirilis oleh emiten di Bursa Efek Indonesia (BEI/IDX).

### Prinsip Verifikasi:
1. **Evidence-Backed:** Setiap angka kanonikal harus dapat ditelusuri kembali (*traceable*) ke elemen tag `instance.xbrl` atau halaman tabel laporan keuangan PDF resmi.
2. **Strict Scope Integrity:** Pastikan status konsolidasi (`Group entity` vs `Single entity`) tidak tertukar.
3. **No Coercion:** Dilarang memaksa angka menjadi seimbang atau mengubah nilai `nil` menjadi `0` tanpa bukti tertulis di CALK (Catatan Atas Laporan Keuangan).
4. **Audit Trail Logging:** Semua perbedaan komparatif (revisi/restatement) harus dicatat pada log audit, bukan menimpa data periode sebelumnya.

---

## 2. Dokumen Sumber Acuan

Saat melakukan review, siapkan artefak berikut:
1. **File Ekstraksi Pipeline:** Normalized facts output atau ringkasan validasi (`scripts/validate_quality.py`).
2. **File XBRL Instance:** `instance.xbrl` di dalam arsip `instance.zip`.
3. **Laporan Keuangan Publik (PDF/XLSX):** Laporan resmi yang diunduh dari situs IDX (`idx.co.id`) pada bagian *Financial Statements & Annual Report*.

---

## 3. Tujuh Tahapan Rekonsiliasi (7-Point Tie-Out Checklist)

### Tahap 1: Metadata Entitas, Entry Point, & Lingkup Konsolidasi
- [ ] **Ticker & Nama Emiten:** Pastikan simbol emiten (`EntityTradingSymbol`) dan nama perusahaan (`EntityRegistrantName`) cocok dengan dokumen registrasi.
- [ ] **Mata Uang Pelaporan:**
  - Periksa fakta DEI `DescriptionOfPresentationCurrency`.
  - Jika `Rupiah / IDR`, seluruh akun keuangan moneter harus ber-unit `iso4217:IDR`.
  - Jika emiten menggunakan mata uang asing (contoh: **AADI** menggunakan `Dollar Amerika / USD`), pastikan pipeline tidak mengonversi atau menganggapnya IDR tanpa kurs histori.
- [ ] **Lingkup Konsolidasi (Reporting Scope):**
  - Periksa fakta DEI `WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities`.
  - 9 emiten pilot adalah `Group entity` (laporan keuangan konsolidasian).
  - **BRIS** adalah `Single entity` (laporan entitas induk saja). Pastikan status ini dihormati dan tidak dianggap konsolidasi.
- [ ] **Entry Point Taksonomi:**
  - `.../ep/E24/general`: Emiten komersial umum (AADI, ANTM, ASII, CPIN, PWON, SMGR, UNTR).
  - `.../ep/E24/infrastructure`: Emiten infrastruktur/telco (TLKM).
  - `.../ep/E24/financesharia`: Emiten perbankan (BBCA, BRIS). *Ingat: nama entry point ini adalah taksonomi teknis, bukan penentu klasifikasi bisnis/syariah.*

---

### Tahap 2: Laporan Posisi Keuangan (Balance Sheet Tie-Out)
- [ ] **Persamaan Fundamental Neraca:**
  - Untuk non-bank: $\text{Total Assets} = \text{Total Liabilities} + \text{Total Equity}$. Selisih harus $\le \text{Rp1.000}$ (faktor pembulatan penyajian).
  - Untuk perbankan (`financesharia` - BBCA & BRIS):
    $$\text{Total Assets} = \text{Total Liabilities} + \text{TemporarySyirkahFunds (DST)} + \text{Total Equity}$$
    *Pastikan Dana Syirkah Temporer diperhitungkan; jika diabaikan, neraca akan terlihat selisih secara keliru.*
- [ ] **Klasifikasi Aset (Lancar vs Tidak Lancar):**
  - Untuk emiten umum: $\text{Current Assets} + \text{Non-Current Assets} = \text{Total Assets}$.
  - Untuk perbankan: Wajar jika aset lancar dan aset tidak lancar kosong (*unclassified balance sheet*).
- [ ] **Klasifikasi Liabilitas (Jangka Pendek vs Panjang):**
  - Untuk emiten umum: $\text{Current Liabilities} + \text{Non-Current Liabilities} = \text{Total Liabilities}$.
  - Untuk perbankan: Wajar jika tidak dipisahkan lancar/tidak lancar.
- [ ] **Atribusi Ekuitas:**
  - $\text{Total Equity} = \text{Equity Parent} + \text{Non-Controlling Interests (NCI)}$.
  - Verifikasi bahwa modal saham, agio, dan saldo laba yang diatribusikan ke induk tidak tertukar dengan porsi minoritas.

---

### Tahap 3: Laporan Laba Rugi Komprehensif (Income Statement Tie-Out)
- [ ] **Laba Bruto (Gross Profit):**
  - Untuk emiten non-bank/non-telco: $\text{Gross Profit} = \text{Revenue} - \text{Cost of Revenue}$.
  - Untuk TLKM (telco): Tidak ada akun beban pokok penjualan generik; jangan memaksakan penciptaan akun laba bruto.
  - Untuk perbankan: Beban pendapatan bunga/operasional mengikuti format perbankan; tidak memiliki laba bruto.
- [ ] **Atribusi Laba Bersih Periode Berjalan:**
  - $\text{Profit/Loss Total} = \text{Profit Attributable to Parent} + \text{Profit Attributable to NCI}$.
- [ ] **Laba Per Saham (EPS):**
  - Pastikan unit EPS adalah `IDRPerShares` atau `USDPerShares` (bukan unit mata uang saja).
  - Cocokkan angka EPS dasar dengan tabel laba rugi di laporan publik.

---

### Tahap 4: Laporan Arus Kas (Cash Flow Statement Tie-Out)
- [ ] **Komposisi Arus Kas Bersih:**
  - $\text{Net Cash Change} = \text{Cash Flow Operating} + \text{Cash Flow Investing} + \text{Cash Flow Financing}$.
- [ ] **Rekonsiliasi Kas Akhir Periode:**
  - Bandingkan akun kas neraca (`CashAndCashEquivalents`) dengan kas akhir arus kas (`CashAndCashEquivalentsCashFlows`).
  - Jika terdapat perbedaan (seperti pada **CPIN** dan **PWON**), periksa catatan kaki kas:
    * Apakah ada kas yang dibatasi penggunaannya (*restricted cash*)?
    * Apakah ada deposito berjangka jaminan yang jatuh temponya $> 3$ bulan?
    * Apakah ada cerukan bank (*bank overdraft*)?
  - Jika terkonfirmasi di CALK, berikan catatan review tanpa mengubah angka dasar.

---

### Tahap 5: Konteks Waktu & Dimensi (Dimension Bleeding Prevention)
- [ ] **Konteks Tanpa Dimensi (*Undimensioned Context*):**
  - Pastikan fakta utama yang dipetakan berasal dari context standar (`CurrentYearInstant`, `CurrentYearDuration`).
  - Pastikan fakta tidak memiliki akhiran member dimensi disclosure (contoh: `_CommonStocksMember`, `_ThirdPartiesMember`) kecuali konsep kanonikal memang ditujukan untuk sub-komponen tersebut.
- [ ] **Tanggal Periode:**
  - Durasi Q2 harus dimulai dari `YYYY-01-01` sampai `YYYY-06-30` (kumulatif YTD, bukan Q2 *stand-alone*).

---

### Tahap 6: Angka Komparatif & Restatement (Cross-Period Audit)
- [ ] **Pemeriksaan Angka Periode Sebelumnya:**
  - Bandingkan angka kolom komparatif tahun lalu pada laporan saat ini dengan laporan asli yang diterbitkan tahun lalu.
  - Jika terjadi perbedaan (contoh: **TLKM** merevisi laba bersih 2025Q1 dan 2025Q2 pada penyajian komparatif 2026):
    * Jangan menimpa record filing lama di database!
    * Buat flag `Restatement Detected` pada filing komparatif.
    * Catat alasan penyajian kembali dari CALK penyajian kembali laporan keuangan.
- [ ] **Kontinuitas Saldo Akhir Tahun Lalu:**
  - Pastikan saldo akhir tahun lalu (`PriorEndYearInstant`) pada Q1 sama persis dengan saldo akhir tahun lalu pada Q2.

---

### Tahap 7: Catatan Utang & Pembiayaan (Handoff DA-4)
- [ ] **Nil vs Zero:**
  - Periksa pos sukuk atau obligasi jangka panjang (contoh: `LongTermSukuk`).
  - Jika bernilai `nil` (`is_nil="true"`), pertahankan sebagai *unreported/not disclosed*; jangan diubah menjadi Rp0.
- [ ] **Daftar Bank/Kreditor Material:**
  - Pastikan rincian pinjaman bank jangka pendek dan jangka panjang merujuk pada CALK pengungkapan utang perbankan.

---

## 4. Matriks Keputusan & Prosedur Eskalasi

| Hasil Pemeriksaan | Status Akhir | Tindakan Lanjutan |
| :--- | :--- | :--- |
| Seluruh 7 tahapan lolos tanpa selisih di atas toleransi. | **`VERIFIED`** | Berikan persetujuan (*approval*) di HISSA Ops Review; izinkan data dipublikasikan ke HISSA Core. |
| Ditemukan perbedaan yang dapat dijelaskan CALK (misal: restatement komparatif TLKM, selisih kas terikat CPIN/PWON, defisit modal). | **`REVIEW_REQUIRED`** | Cantumkan catatan rekonsiliasi pada formulir review; mintakan konfirmasi peer-review DA-2/CTO sebelum rilis. |
| Persamaan neraca tidak seimbang di luar pembulatan, duplikasi nilai konflik, scope tidak valid, atau konversi ilegal nilai `nil`. | **`FAILED`** | Tolak filing dari antrean publikasi; eskalasi ke DA-1 (ekstraksi) atau DA-2 (pemetaan) untuk perbaikan rule. |

---

## 5. Formulir Checklist Rekonsiliasi Per Filing (Template)

```text
================================================================================
HISSA FINANCIAL DATA ENGINE — MANUAL RECONCILIATION FORM
================================================================================
Filing ID       : [ Conto: ANTM-2026Q2-xxxx ]
Ticker / Periode: [ ANTM / 2026Q2 ]
Reviewer        : [ Nama Analis DA-3 ]
Tanggal Review  : [ YYYY-MM-DD ]
Status Rekomendasi: [ VERIFIED / REVIEW_REQUIRED / FAILED ]

[1] ENTITY & SCOPE
  - Ticker & Entity Name Match        : [ PASS / FAIL ]
  - Currency                          : [ IDR / USD ] -> [ PASS / FAIL ]
  - Scope                             : [ Group / Single ] -> [ PASS / FAIL ]
  - Entry Point                       : [ general / financesharia / infrastructure ]

[2] BALANCE SHEET
  - Assets = Liab + (DST) + Equity    : [ PASS / FAIL ] (Selisih: ________)
  - Asset Classification (Lancar/Tdk) : [ PASS / FAIL / NA ]
  - Liab Classification (Pdk/Pjg)     : [ PASS / FAIL / NA ]
  - Equity = Parent + NCI             : [ PASS / FAIL ]

[3] INCOME STATEMENT
  - Revenue - COGS = Gross Profit     : [ PASS / FAIL / NA ]
  - Profit = Parent + NCI             : [ PASS / FAIL ]
  - EPS Unit (Per-share)              : [ PASS / FAIL ]

[4] CASH FLOW
  - Net Cash Change = CFO + CFI + CFF : [ PASS / FAIL ]
  - BS Cash vs CF Cash Reconciled     : [ PASS / WARN ] (Selisih: ________)

[5] COMPARATIVE & RESTATEMENT
  - Prior Year Comparison Consistent  : [ PASS / WARN ]
  - Catatan Restatement (jika ada)    : _________________________________________

CATATAN & JUSTIFIKASI REVIEWER:
________________________________________________________________________________
________________________________________________________________________________

Tanda Tangan Reviewer: ____________________   Tanggal: ______________
================================================================================
```
