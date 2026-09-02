# Source Issues Register — DA-1 Pilot Dataset

Tanggal: 1 September 2026  
Cakupan: 40 `instance.zip` pada `data/raw` dan hasil extraction di `data/extracted`.

## Cara membaca register

Issue di bawah adalah variasi, gap, atau risiko sumber yang didukung evidence. Tidak semua issue berarti source IDX salah. Kolom *handling* mendefinisikan perilaku pipeline yang aman; tidak ada issue yang boleh ditutup dengan mengubah raw fact secara manual.

| ID | Severity | Evidence | Dampak | Handling yang disepakati | Status |
| --- | --- | --- | --- | --- | --- |
| SRC-001 | WARN | Semua 40 ZIP hanya berisi `instance.xbrl` dan `Taxonomy.xsd`; tidak ada PDF, XLSX, atau iXBRL presentation artifact dalam `data/raw`. | Rekonsiliasi visual terhadap tabel laporan belum dapat dilakukan dari repository ini. | Verifikasi structural terhadap instance XBRL boleh dilakukan. Simpan/collect URL dan hash PDF/XLSX/iXBRL pada Source Map sebelum memberi status visual-reconciled. | OPEN |
| SRC-002 | ERROR jika diabaikan | BBCA dan BRIS sama-sama memakai entry point `.../ep/E24/financesharia`. | Nama entry point dapat menghasilkan klasifikasi bisnis/syariah yang salah. | Entry point hanya disimpan sebagai metadata teknis. Klasifikasi syariah harus memakai evidence DA-4, bukan nama entry point. | CONTROL_DEFINED |
| SRC-003 | ERROR jika diabaikan | Fact DEI scope: 36/40 filing `Entitas grup / Group entity`; 4/40 `Entitas tunggal / Single entity`, seluruhnya BRIS. | Pemilihan fact dapat salah scope apabila seluruh issuer diasumsikan consolidated/group. | Parse dan simpan fact `WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities`. Group/single merupakan scope filing; jangan infer dari context name atau entry point. | CONTROL_DEFINED |
| SRC-004 | WARN | `LongTermSukuk` pada `CurrentYearInstant`: 29 fact `nil`; 4 fact non-nil dengan nilai `0` (ASII empat periode). | Mengubah `nil` menjadi 0 akan menciptakan data yang tidak berasal dari source; menganggap concept ada sebagai outstanding juga salah. | Pertahankan `is_nil`, raw value, dan unit. Nilai `nil` masuk `REVIEW_REQUIRED` untuk metrik/outstanding sampai rule bisnis disetujui. | CONTROL_DEFINED |
| SRC-005 | WARN | Variasi struktur besar: 1.205–3.943 context dan 51–97 linkbase reference per filing; entry point juga tiga jenis. | Parser/mapping yang hard-code pada satu issuer/periode akan gagal atau memilih disclosure detail secara salah. | Gunakan parser generik; pilih fact berdasarkan concept + namespace + context rule + dimensions, bukan urutan file atau jumlah node. | CONTROL_DEFINED |
| SRC-006 | WARN | Raw path hanya memuat ticker/periode; belum ada IDX filing URL, publication timestamp, source filing ID, atau revision chain. | Restatement/revision belum dapat dibedakan secara andal; risiko overwrite nilai historis saat ingest ulang. | Bangun Source Map dengan source URL/ID, publication date, document type, dan hash. Simpan setiap revision sebagai filing baru; jangan overwrite raw facts. | OPEN |

## Non-issue yang sudah dicek

- **40/40 ZIP valid** dan **40/40 `instance.xbrl` berhasil diparse**.
- Setiap filing memiliki pola tujuh context utama tanpa dimensi; detail disclosure berada pada context berdimensi.
- Lima fact representative telah dicocokkan kembali ke elemen sumber di `instance.xbrl`; lihat `docs/source-verification-notes.md`.

## Trigger eskalasi

- Jika scope DEI tidak tersedia atau nilainya tidak dikenali: `REVIEW_REQUIRED`, eskalasi DA-1 + DA-2.
- Jika data revision hanya diketahui dari halaman/artefak luar repository: jangan overwrite filing lama; buat issue untuk Source Map dan review CTO.
- Jika kebutuhan PDF/XLSX/iXBRL menyangkut akses, lisensi, atau redistribusi: eskalasi CTO/business/legal owner sesuai `project.md`.

## Tindak lanjut yang belum dilakukan

1. Melengkapi Source Map dengan identitas dan URL filing IDX untuk mengatasi SRC-001 dan SRC-006.
2. Menambahkan test fixture untuk `group`, `single`, `nil`, dan `zero` agar DA-3 dapat menguji perilaku parser/validation.
