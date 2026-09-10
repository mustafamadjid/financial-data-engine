# feat(ops): buat Pipeline page untuk HISSA Ops MVP

## Fase dan konteks

- **Fase target:** HISSA Ops + intelligence pilot, 11-14 September 2026.
- **Referensi:** `HISSA_Financial_Data_Engine_Internship_Work_Brief_v1.1.pdf`, `rules-design.md`, dan pipeline operations runbook.
- Pipeline backend sudah menyediakan status filing, stage execution, error context yang telah disanitasi, artifact, history, retry, dan reprocess. Issue ini membangun halaman Ops yang mengekspos capability tersebut melalui workflow yang aman dan dapat diaudit.

## Tujuan

Menyediakan page `/ops/pipeline` berbasis Vue 3 + Inertia yang memungkinkan pengguna Ops:

1. menemukan filing yang sedang diproses atau bermasalah;
2. melihat status keseluruhan dan status per stage `DOWNLOAD -> PARSE -> NORMALIZE -> VALIDATE -> PUBLISH`;
3. membuka detail, artifact, execution attempt, dan audit history tanpa membocorkan field private;
4. menjalankan retry atau reprocess hanya melalui action yang diizinkan, dengan alasan dan audit trail.

Page harus menjadi read/operate surface untuk pipeline yang sudah ada, bukan tempat menjalankan algoritma finansial atau mengubah data secara manual.

## Scope implementasi

### 1. Pipeline overview page

- Route authenticated `GET /ops/pipeline` dengan Inertia page `Pipeline/Index`.
- Header HISSA Ops dan status refresh/monitoring.
- Summary cards untuk:
  - total filings;
  - active processing;
  - failed quality status;
  - verified;
  - review required;
  - pending;
  - failed executions.
- Tabel filing dengan identitas stable:
  - filing ID dan issuer;
  - report type, fiscal year/period, revision;
  - processing stage dan quality status;
  - status per stage;
  - last processed time;
  - error summary jika ada;
  - action yang tersedia untuk filing tersebut.

### 2. Search, filter, sorting, dan pagination

- Server-side search berdasarkan filing ID atau issuer code.
- Filter processing stage, quality status, dan period.
- Sorting berdasarkan last processed atau issuer.
- Pilihan page size `25`, `50`, dan `100`.
- State filter tersimpan di URL agar dapat direfresh/share tanpa kehilangan konteks.
- Pagination deterministic dan tidak mengubah identitas/revision filing.

### 3. Monitoring dan state UI

- Polling aktif setiap 10 detik ketika ada stage `QUEUED` atau `RUNNING`.
- Polling idle setiap 60 detik ketika tidak ada pekerjaan aktif.
- Polling berhenti ketika tab browser tidak visible.
- Sediakan loading/skeleton, empty state, filtered-empty state, retry request, permission error, dan generic error state.
- Semua action penting dapat diakses via keyboard dan memiliki label/feedback yang jelas.

### 4. Detail filing dan history

- Detail filing dibuka dalam drawer tanpa meninggalkan halaman utama.
- Tampilkan current pipeline run, correlation ID, dependency versions, stage attempts, latest error, dan metadata filing.
- Tampilkan artifact yang memang dimiliki filing dan sediakan download melalui endpoint yang tervalidasi.
- Tampilkan history newest-first yang menggabungkan pipeline run, job attempt, dan audit event dengan pagination.
- Jangan tampilkan `storage_path`, idempotency key, job class, raw queue payload, secret, atau private error context.
- Drawer mendukung close button, Escape, dan mengembalikan focus ke elemen pemanggil.

### 5. Retry dan reprocess

- Retry hanya tersedia untuk failed attempt yang diklasifikasikan transient dan memang memiliki action capability `retry`.
- Retry menggunakan action endpoint yang ada, mempertahankan logical input/run, dan menghasilkan attempt baru.
- Reprocess hanya tersedia jika filing memiliki capability `reprocess`.
- Reprocess harus memilih start stage yang valid, mewajibkan reason, memanggil service backend, dan membuat run/correlation baru sesuai kontrak pipeline.
- Tampilkan status accepted atau error dari operation; page tidak boleh menjalankan stage pipeline secara synchronous.
- Action wajib mengikuti authentication, authorization, CSRF, idempotency, overlap protection, dan audit trail backend.

### 6. Backend bridge dan contract

- Pertahankan endpoint JSON terpisah untuk list, summary, detail, history, artifact, retry, dan reprocess di bawah prefix Ops.
- Gunakan read model/resource yang bounded dan query server-side; jangan mengirim model/database payload mentah ke browser.
- Pertahankan error envelope yang stabil, termasuk validation error, permission error, not found, dan operation conflict.
- Reuse policy `PipelineFilingPolicy` dan capability response untuk menentukan action yang boleh ditampilkan.

## Acceptance criteria

- [ ] User yang belum authenticated tidak dapat mengakses page dan endpoint data pipeline.
- [ ] User authenticated dapat membuka `/ops/pipeline` dan melihat summary serta tabel filing dari endpoint server-side.
- [ ] Tabel menampilkan lima stage pipeline dengan status `NOT_STARTED`, `QUEUED`, `RUNNING`, `SUCCEEDED`, atau `FAILED` secara konsisten.
- [ ] Search, filter, sort, page size, pagination, dan URL state menghasilkan request/query yang benar serta tidak melakukan filtering utama di client.
- [ ] Filing dengan revision berbeda tetap muncul sebagai identitas/row yang berbeda dan diurutkan secara deterministic.
- [ ] Polling active/idle dan visibility behavior sesuai interval yang ditetapkan, tanpa polling saat tab hidden.
- [ ] Loading, empty, filtered-empty, permission denied, request failure, dan malformed response memiliki UI yang aman dan dapat dipahami.
- [ ] Detail drawer menampilkan run/attempt/error/artifact/history yang relevan dan tidak membocorkan field private.
- [ ] Artifact hanya dapat di-download jika artifact tersebut dimiliki filing, lolos validasi hash, dan response memakai filename/content type yang aman.
- [ ] Retry tidak tersedia untuk action yang tidak diizinkan, mengirim reason bila diberikan, dan menampilkan hasil operation atau error secara jelas.
- [ ] Reprocess mewajibkan stage dan reason, tidak mengedit database langsung, serta menampilkan operation accepted setelah backend membuat run baru.
- [ ] Semua mutation melewati auth, policy, CSRF, overlap/idempotency protection, dan audit logging yang sudah ada.
- [ ] Test backend Ops dan test frontend pipeline lulus; production build frontend berhasil.
- [ ] Tidak ada perubahan pada financial semantics, pipeline job contract, published snapshot contract, atau koneksi HISSA Core production.

## Rencana pekerjaan

- [ ] Konfirmasi route, policy, request validation, resource, dan endpoint contract yang menjadi dependency page.
- [ ] Lengkapi composable query/filter/action dan decoder response typed.
- [ ] Implementasikan overview page, summary, table, stage cell, filter, pagination, dan URL synchronization.
- [ ] Implementasikan active/idle polling dan behavior saat tab hidden.
- [ ] Implementasikan detail drawer, artifact download, dan history panel dengan sanitization boundary.
- [ ] Implementasikan retry/reprocess dialog dengan capability guard, validation, pending state, success state, dan error state.
- [ ] Tambahkan atau lengkapi unit/component/feature tests untuk happy path, empty/error state, permission, filtering, polling, detail/history, retry, dan reprocess.
- [ ] Jalankan build dan verifikasi akhir bersama `git diff --check`.

## Out of scope fase ini

- Financial Review page dan workflow approval/mapping.
- Concept Mapping page dan automatic mapping/correction.
- Data Quality dashboard/rule editor.
- Fundamental metrics, debt/sharia intelligence, dan analytics page.
- WebSocket/live streaming atau redesign queue/pipeline semantics.
- Direct write ke database HISSA Core, production deployment, atau production credentials.
- Manual database edit sebagai mekanisme recovery.
- Full IDX backfill atau perluasan dataset di luar approved pilot.

## Definition of done

- Page dapat didemokan menggunakan lebih dari satu filing pilot, termasuk filing sukses, aktif, gagal, dan review-required.
- Semua acceptance criteria terverifikasi melalui test atau bukti UI yang dapat direproduksi dari repository.
- Tidak ada secret, raw payload, private path, atau data unpublished yang bocor ke response/UI/log.
- Dokumentasi/runbook diperbarui bila behavior operasi atau cara menjalankan page berubah.
- Known limitation dan pekerjaan lanjutan untuk Financial Review, Mapping, Data Quality, serta hardening dicatat sebagai backlog/issue terpisah.

## Bukti yang dilampirkan pada penyelesaian

- Link commit/PR dan issue ini.
- Hasil test backend Ops dan frontend pipeline.
- Hasil `bun run build`.
- Screenshot atau rekaman singkat page pada state normal, failed/recovery, detail, dan history.
- Daftar limitation atau keputusan yang masih memerlukan review CTO/core team.
