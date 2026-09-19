# HISSA Financial Data Engine — DA-3 Validation Rulebook

| Document | HISSA Financial Data Engine — Data Quality & Validation Rulebook |
| :--- | :--- |
| **Owner** | DA-3 Data Quality & Reconciliation Analyst |
| **Audience** | DA-1, DA-2, DA-4, Full Stack Engineer, CTO / System Owner |
| **Version** | 1.0 |
| **Date** | 07 September 2026 |
| **Status** | ACTIVE / BASELINE |

---

## 1. Misi dan Prinsip Data Quality

Misi utamanya adalah **mencegah data yang salah, terdistorsi, atau tidak dapat dipertanggungjawabkan masuk ke lingkungan production HISSA**. Data fundamental yang dipublikasikan harus teruji keabsahannya secara matematis, semantik, konteks, dan regulasi akuntansi (PSAK/IFRS).

### Prinsip Operasional:
1. **Zero Silent Guessing:** Data tidak boleh diubah, ditebak, atau dipaksakan balance tanpa sumber bukti formal.
2. **Deterministic Validation:** Setiap rule memiliki formula matematis, toleransi eksplisit, dan severity yang terdefinisi.
3. **Industry & Entry-Point Awareness:** Membedakan perlakuan standar komersial umum (`general`), infrastruktur (`infrastructure`), dan perbankan/keuangan syariah (`financesharia`).
4. **Audit Trail & Immutability:** Anomali komparatif (restatement) tidak boleh menimpa (*overwrite*) data historis, melainkan dicatat sebagai versi baru dengan flag audit.
5. **Quality Gates:** Data hanya dapat mencapai status `VERIFIED` jika bebas dari *blocking error*.

---

## 2. Tingkat Keparahan (Severity) dan Status Kualitas

### 2.1 Klasifikasi Severity
* **`ERROR` (Blocking):** Pelanggaran fatal terhadap prinsip dasar akuntansi atau integritas data (misal: neraca tidak seimbang di luar toleransi, duplikasi nilai berkonflik, context/scope tidak sah). Filing dengan status `ERROR` **DITOLAK** dari publikasi otomatis.
* **`WARN` (Non-Blocking / Attention Required):** Indikasi anomali material atau kondisi khusus yang memerlukan verifikasi analis (misal: restatement komparatif terdeteksi, ekuitas negatif akibat defisit, perbedaan kas akhir neraca vs arus kas).
* **`INFO` (Informational / Audit Log):** Pencatatan variasi teknis yang valid secara regulasi (misal: pelaporan dalam USD, scope entitas tunggal, pelaporan neraca *unclassified* pada bank).

### 2.2 Quality Scoring Decision Matrix

| Filing Status | Definisi | Kriteria Kelulusan | Aksi Pipeline |
| :--- | :--- | :--- | :--- |
| **`VERIFIED`** | Data lolos seluruh uji akuntansi, hierarki, konteks, dan skala secara penuh. | `0 ERROR`, `0 WARN` (atau semua WARN telah ditinjau dan disetujui analis). | Diizinkan langsung dipublikasikan ke HISSA Core / fundamental database. |
| **`REVIEW_REQUIRED`** | Data memiliki peringatan (`WARN`) yang memerlukan verifikasi manual analis. | `0 ERROR`, `≥ 1 WARN`. | Masuk ke antrean HISSA Ops Review Dashboard; penahanan publikasi otomatis sampai analis memberikan *sign-off*. |
| **`FAILED`** | Integritas data rusak atau persamaan fundamental akuntansi dilanggar. | `≥ 1 ERROR`. | Pipeline publikasi dibatalkan (*aborted*); log kesalahan dikirim ke engineering/DA. |

---

## 3. Katalog Aturan Validasi (Validation Rules Catalog)

### 3.1 Accounting Equation Rules (ACC)

#### `ACC-001`: Balance Sheet Fundamental Equation (Aset = Liabilitas + Ekuitas)
* **Kategori:** Accounting Equation
* **Deskripsi:** Memastikan total aset sama dengan total liabilitas ditambah total ekuitas. Untuk entitas bank syariah / perbankan dengan bisnis syariah (`financesharia`), memperhitungkan Dana Syirkah Temporer (`TemporarySyirkahFunds` / DST) sesuai PSAK 101/105.
* **Formula:**
  - Non-Bank / General / Infrastructure:
    $$\Delta = |\text{total\_assets} - (\text{total\_liabilities} + \text{total\_equity})|$$
  - Bank / Sharia Finance (`financesharia`):
    $$\Delta = |\text{total\_assets} - (\text{total\_liabilities} + \text{temporary\_syirkah\_funds} + \text{total\_equity})|$$
* **Inputs:** `total_assets`, `total_liabilities`, `total_equity`, `temporary_syirkah_funds`, `entry_point`
* **Toleransi:** $\Delta \le 1.000$ (satuan mata uang pelaporan, untuk mengakomodasi pembulatan pada laporan keuangan).
* **Severity:** `ERROR`
* **Expected Behavior:**
  - Lolos jika $\Delta \le \text{toleransi}$.
  - Gagal (`ERROR`) jika $\Delta > \text{toleransi}$. Filing berstatus `FAILED`.

---

### 3.2 Hierarchy & Subtotal Rules (HRY)

#### `HRY-001`: Total Equity Attribution Breakdown
* **Kategori:** Hierarchy & Subtotals
* **Deskripsi:** Total ekuitas harus sama dengan ekuitas yang dapat diatribusikan kepada entitas induk ditambah kepentingan non-pengendali (NCI).
* **Formula:**
  $$\Delta = |\text{total\_equity} - (\text{equity\_parent} + \text{non\_controlling\_interests})|$$
  *(Catatan: jika NCI bernilai nil/absen, NCI dianggap 0)*
* **Inputs:** `total_equity`, `equity_parent`, `non_controlling_interests`
* **Toleransi:** $\Delta \le 1.000$
* **Severity:** `ERROR`
* **Expected Behavior:** Jika selisih $> 1.000$, gagalkan validasi.

#### `HRY-002`: Asset Classification Breakdown (Lancar + Tidak Lancar)
* **Kategori:** Hierarchy & Subtotals
* **Deskripsi:** Pada laporan posisi keuangan yang terklasifikasi (*classified balance sheet*), jumlah aset lancar dan aset tidak lancar harus sama dengan total aset.
* **Formula:**
  $$\Delta = |\text{total\_assets} - (\text{current\_assets} + \text{non\_current\_assets})|$$
* **Inputs:** `total_assets`, `current_assets`, `non_current_assets`, `entry_point`
* **Pengecualian Industri:** Tidak berlaku untuk industri perbankan/keuangan (`financesharia`) yang menggunakan neraca berdasarkan urutan likuiditas (*unclassified balance sheet*).
* **Toleransi:** $\Delta \le 1.000$
* **Severity:** `ERROR` (untuk emiten industri umum/infrastruktur) / `INFO` (jika tidak dilaporkan pada bank).
* **Expected Behavior:** Jika emiten non-bank tidak balance, `ERROR`. Jika bank tidak menyajikan aset lancar, catat `INFO: Unclassified balance sheet`.

#### `HRY-003`: Liability Classification Breakdown (Jangka Pendek + Panjang)
* **Kategori:** Hierarchy & Subtotals
* **Deskripsi:** Pada neraca terklasifikasi, jumlah liabilitas jangka pendek dan liabilitas jangka panjang harus sama dengan total liabilitas.
* **Formula:**
  $$\Delta = |\text{total\_liabilities} - (\text{current\_liabilities} + \text{non\_current\_liabilities})|$$
* **Inputs:** `total_liabilities`, `current_liabilities`, `non_current_liabilities`, `entry_point`
* **Pengecualian Industri:** Tidak berlaku untuk institusi perbankan (`financesharia`).
* **Toleransi:** $\Delta \le 1.000$
* **Severity:** `ERROR` (untuk non-bank) / `INFO` (untuk bank).
* **Expected Behavior:** Verifikasi klasifikasi liabilitas non-bank secara ketat.

#### `HRY-004`: Gross Profit Subtotal (Laba Bruto)
* **Kategori:** Hierarchy & Subtotals
* **Deskripsi:** Laba bruto harus sama dengan pendapatan dikurangi beban pokok pendapatan.
* **Formula:**
  $$\Delta = |\text{gross\_profit} - (\text{revenue} - \text{cost\_of\_revenue})|$$
* **Inputs:** `gross_profit`, `revenue`, `cost_of_revenue`
* **Pengecualian Industri:** Emiten perbankan dan infrastruktur/telekomunikasi yang tidak menyajikan akun laba bruto dikecualikan dari aturan ini.
* **Toleransi:** $\Delta \le 1.000$
* **Severity:** `ERROR` (jika ketiga field ada namun tidak konsisten).
* **Expected Behavior:** Menjamin kebenaran perhitungan laba kotor komersial.

#### `HRY-005`: Net Profit Attribution Breakdown
* **Kategori:** Hierarchy & Subtotals
* **Deskripsi:** Total laba (rugi) periode berjalan harus sama dengan laba (rugi) yang diatribusikan ke pemilik entitas induk ditambah ke kepentingan non-pengendali.
* **Formula:**
  $$\Delta = |\text{profit\_loss} - (\text{profit\_loss\_parent} + \text{profit\_loss\_nci})|$$
* **Inputs:** `profit_loss`, `profit_loss_parent`, `profit_loss_nci`
* **Toleransi:** $\Delta \le 1.000$
* **Severity:** `ERROR`
* **Expected Behavior:** Selisih $> 1.000$ memicu penolakan filing.

#### `HRY-006`: Cash Flow Subtotal Addition
* **Kategori:** Hierarchy & Subtotals
* **Deskripsi:** Kenaikan (penurunan) bersih kas dan setara kas sebelum efek kurs harus sama dengan penjumlahan arus kas operasi, investasi, dan pendanaan.
* **Formula:**
  $$\Delta = |\text{net\_change\_cash} - (\text{cash\_flow\_operating} + \text{cash\_flow\_investing} + \text{cash\_flow\_financing})|$$
* **Inputs:** `net_change_cash`, `cash_flow_operating`, `cash_flow_investing`, `cash_flow_financing`
* **Toleransi:** $\Delta \le 1.000$
* **Severity:** `ERROR`
* **Expected Behavior:** Memverifikasi integritas matematika laporan arus kas.

---

### 3.3 Context & Dimensional Rules (CTX)

#### `CTX-001`: Periodicity & Temporal Consistency
* **Kategori:** Context & Periodicity
* **Deskripsi:** Fakta instan harus memiliki tanggal pelaporan tepat pada akhir periode (misal: 31 Maret untuk Q1, 30 Juni untuk Q2). Fakta durasi harus memiliki tanggal mulai 1 Januari (YTD) dan tanggal akhir sesuai akhir periode.
* **Inputs:** `period_type`, `instant_date`, `start_date`, `end_date`, `period`
* **Severity:** `ERROR`
* **Expected Behavior:** Fakta dengan format tanggal atau cakupan periode tidak valid ditolak.

#### `CTX-002`: Concept Period Type Alignment
* **Kategori:** Context & Periodicity
* **Deskripsi:** Konsep neraca wajib berpasangan dengan context `instant`; konsep laba rugi dan arus kas wajib berpasangan dengan context `duration`.
* **Inputs:** `canonical_concept`, `period_type` (dari dictionary) vs `period_type` (dari context)
* **Severity:** `ERROR`
* **Expected Behavior:** Mencegah percampuran konsep instan ke durasi atau sebaliknya.

#### `CTX-003`: Undimensioned Default Fact Enforcement
* **Kategori:** Context & Dimensions
* **Deskripsi:** Nilai total statement kanonikal default harus diambil dari konteks tanpa dimensi (*undimensioned context*). Konteks berdimensi (memiliki `member_qname`) adalah rincian disclosure dan tidak boleh menggantikan total default.
* **Inputs:** `context_id`, `dimensions_json`
* **Severity:** `ERROR`
* **Expected Behavior:** Menolak pengambilan nilai anggota dimensi sebagai total laporan keuangan.

#### `CTX-004`: Duplicate Fact Conflict Detection
* **Kategori:** Context Integrity
* **Deskripsi:** Dalam satu filing, tidak boleh ada dua fakta dengan `source_concept`, `context_id`, dan `unit_id` yang sama namun memiliki `value_raw` yang berbeda.
* **Inputs:** `source_concept`, `context_id`, `unit_id`, `value_raw`
* **Severity:** `ERROR`
* **Expected Behavior:** Konflik duplikasi menyebabkan status filing `FAILED`.

---

### 3.4 Scale, Currency, & Plausibility Rules (SCL)

#### `SCL-001`: Statement Currency Uniformity
* **Kategori:** Scale & Currency
* **Deskripsi:** Seluruh fakta moneter dalam satu filing harus memiliki mata uang yang seragam dan sesuai dengan deklarasi DEI `DescriptionOfPresentationCurrency`.
* **Inputs:** `unit_id`, `currency`, DEI presentation currency
* **Severity:** `ERROR`
* **Expected Behavior:** Mencegah percampuran IDR dan USD dalam filing yang sama.

#### `SCL-002`: EPS Unit Enforcement
* **Kategori:** Scale & Currency
* **Deskripsi:** Nilai laba per saham (EPS) harus menggunakan unit per saham (contoh: `IDRPerShares` atau `USDPerShares`), bukan unit moneter murni atau unit saham murni.
* **Inputs:** `canonical_concept` (`eps_*`), `unit_id`
* **Severity:** `ERROR`
* **Expected Behavior:** Menjamin denominator EPS tidak hilang.

#### `SCL-003`: Total Assets Non-Negativity & Plausibility
* **Kategori:** Plausibility
* **Deskripsi:** Total aset perusahaan harus bernilai positif dan lebih besar dari nol.
* **Formula:** $\text{total\_assets} > 0$
* **Inputs:** `total_assets`
* **Severity:** `ERROR`
* **Expected Behavior:** Aset $\le 0$ merupakan anomali data kritis; gagalkan validasi.

#### `SCL-004`: Negative Equity Capital Deficit Warning
* **Kategori:** Plausibility
* **Deskripsi:** Ekuitas bernilai negatif menandakan defisiensi modal (defisit melampaui modal disetor). Kondisi ini secara akuntansi dimungkinkan namun berisiko tinggi bagi fundamental.
* **Formula:** $\text{total\_equity} < 0$
* **Inputs:** `total_equity`
* **Severity:** `WARN`
* **Expected Behavior:** Menghasilkan flag `REVIEW_REQUIRED` agar analis memverifikasi status solvabilitas perusahaan.

#### `SCL-005`: Balance Sheet vs Cash Flow Cash Reconciliation
* **Kategori:** Reconciliation
* **Deskripsi:** Membandingkan kas pada neraca (`cash_and_cash_equivalents`) dengan kas akhir pada arus kas (`ending_cash_cash_flow`). Perbedaan material harus diteliti (misal: keberadaan *restricted cash* atau deposito jaminan).
* **Formula:**
  $$\Delta = |\text{cash\_and\_cash\_equivalents} - \text{ending\_cash\_cash\_flow}|$$
* **Inputs:** `cash_and_cash_equivalents`, `ending_cash_cash_flow`
* **Toleransi:** $\Delta == 0$ (jika berbeda, perlu verifikasi CALK).
* **Severity:** `WARN`
* **Expected Behavior:** Jika ada selisih, tandai `REVIEW_REQUIRED` dan catat pada laporan rekonsiliasi.

---

### 3.5 Cross-Period & Restatement Rules (CRX)

#### `CRX-001`: Prior-Period Comparative Consistency & Restatement Audit
* **Kategori:** Cross-Period
* **Deskripsi:** Angka periode lalu yang dilaporkan sebagai angka komparatif pada filing berjalan (`PriorYearDuration` atau `PriorEndYearInstant`) harus dibandingkan dengan angka asli yang dilaporkan pada filing periode lalu (`CurrentYearDuration` atau `CurrentYearInstant`). Jika terjadi perubahan, catat sebagai *Restatement / Revision Event*. Dilarang menimpa (*overwrite*) data historis.
* **Formula:**
  $$\text{Restatement Detected} = (\text{value}_{\text{comparative\_now}} \ne \text{value}_{\text{reported\_originally}})$$
* **Inputs:** `ticker`, `concept`, `comparative_value`, `prior_reported_value`
* **Severity:** `WARN`
* **Expected Behavior:** Tandai status filing menjadi `REVIEW_REQUIRED`, buat entitas restatement audit log, simpan nilai komparatif tanpa merusak histori filing lama.

#### `CRX-002`: Balance Sheet Date Continuity
* **Kategori:** Cross-Period
* **Deskripsi:** Nilai neraca akhir tahun sebelumnya (`PriorEndYearInstant`) pada Q1 tahun berjalan harus sama persis dengan `PriorEndYearInstant` pada Q2 tahun berjalan.
* **Formula:**
  $$\Delta = |\text{value}_{\text{PriorEndYear}(Q1)} - \text{value}_{\text{PriorEndYear}(Q2)}|$$
* **Inputs:** `PriorEndYearInstant` across sequential filings
* **Toleransi:** $\Delta == 0$
* **Severity:** `ERROR`
* **Expected Behavior:** Perubahan angka dasar audit akhir tahun dalam tahun berjalan yang sama tanpa rilis revisi resmi adalah anomali kritis.

---

### 3.6 Scope & Governance Rules (SCP)

#### `SCP-001`: Entity Reporting Scope Verification
* **Kategori:** Scope & Governance
* **Deskripsi:** Memverifikasi kesesuaian konsolidasi antara data DEI (`WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities`) dengan mapping yang diizinkan (`allowed_scope`).
* **Inputs:** DEI Entity Scope, `canonical_dictionary.allowed_scope`
* **Severity:** `ERROR`
* **Expected Behavior:** Jika filing berstatus `Single entity` dipaksakan ke konsep yang hanya mengizinkan `Group entity`, validasi gagal.

#### `SCP-002`: Nil Value Preservation
* **Kategori:** Scope & Governance
* **Deskripsi:** Fakta dengan atribut `is_nil="true"` tidak boleh diubah menjadi nilai `0` secara otomatis. Nilai `nil` harus dipertahankan sebagai status `NOT_REPORTED` / `UNAVAILABLE`.
* **Inputs:** `is_nil`, `value_raw`
* **Severity:** `ERROR`
* **Expected Behavior:** Mencegah penciptaan data palsu dari elemen nihil.

---

## 4. Matriks Ringkasan Aturan Validasi

| Kode Rule | Nama Rule | Severity | Kategori | Pengecualian Industri |
| :--- | :--- | :--- | :--- | :--- |
| `ACC-001` | Balance Sheet Fundamental Balance | `ERROR` | Accounting | Memperhitungkan `TemporarySyirkahFunds` pada `financesharia` |
| `HRY-001` | Equity Parent + NCI = Total Equity | `ERROR` | Hierarchy | - |
| `HRY-002` | Current Assets + Non-Current = Total Assets | `ERROR` | Hierarchy | Dikecualikan untuk Bank (`financesharia`) / *Unclassified* |
| `HRY-003` | Current Liab + Non-Current = Total Liab | `ERROR` | Hierarchy | Dikecualikan untuk Bank (`financesharia`) / *Unclassified* |
| `HRY-004` | Revenue - COGS = Gross Profit | `ERROR` | Hierarchy | Dikecualikan untuk Bank & Telco tanpa akun COGS |
| `HRY-005` | Net Profit Parent + NCI = Total Profit | `ERROR` | Hierarchy | - |
| `HRY-006` | CFO + CFI + CFF = Net Cash Change | `ERROR` | Hierarchy | - |
| `CTX-001` | Periodicity & Temporal Consistency | `ERROR` | Context | - |
| `CTX-002` | Instant/Duration Concept Alignment | `ERROR` | Context | - |
| `CTX-003` | Undimensioned Default Fact Enforcement | `ERROR` | Dimensions | - |
| `CTX-004` | Duplicate Fact Conflict Detection | `ERROR` | Integrity | - |
| `SCL-001` | Statement Currency Uniformity | `ERROR` | Currency | - |
| `SCL-002` | EPS Per-Share Unit Enforcement | `ERROR` | Units | - |
| `SCL-003` | Total Assets Non-Negativity | `ERROR` | Plausibility | - |
| `SCL-004` | Negative Equity Deficit Warning | `WARN` | Plausibility | Memicu `REVIEW_REQUIRED` |
| `SCL-005` | BS Cash vs CF Ending Cash Tie-Out | `WARN` | Reconciliation | Perlu verifikasi *restricted cash* pada CALK |
| `CRX-001` | Prior-Period Comparative Restatement Audit | `WARN` | Cross-Period | Merekam log revisi komparatif (misal: TLKM) |
| `CRX-002` | Balance Sheet Prior Year-End Continuity | `ERROR` | Cross-Period | - |
| `SCP-001` | Scope DEI Entity Alignment | `ERROR` | Governance | Menjaga konsistensi single vs group entity (BRIS) |
| `SCP-002` | Nil Value Preservation | `ERROR` | Governance | Melarang konversi otomatis `nil` ke `0` |

---

## 5. Alur Integrasi dalam Pipeline Produksi

```text
[ Raw XBRL Facts ]
        │
        ▼
[ Canonical Normalization (DA-2 Mapping) ]
        │
        ▼
[ Validation Engine ]
        ├─ Jalankan Rules ACC, HRY, CTX, SCL, CRX, SCP
        │
        ├── Jika ada ERROR ──────────► Status: FAILED (Hold, alert squad)
        │
        ├── Jika ada WARN (tanpa ERROR) ──► Status: REVIEW_REQUIRED (Kirim ke HISSA Ops)
        │                                         │
        │                                         ▼
        │                              [ Analis Review & Approval ]
        │                                         │
        │                                         ▼
        └── Jika 0 ERROR & 0 WARN ──► Status: VERIFIED ──► [ Publish ke HISSA Core ]
```
