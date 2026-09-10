# feat(api): implement Phase 11 HISSA Core read-only integration contract

## Context

Financial Data Engine membutuhkan integration boundary yang stabil untuk HISSA Core tanpa mengakses database production HISSA.

Implementasi harus bersifat contract-first dan hanya mengekspos immutable `published_snapshots` yang telah melewati publish gate dengan status `VERIFIED`.

Referensi utama:

- `.specs/phase-11-api-integration-contract/requirement.md`
- `.specs/phase-11-api-integration-contract/design.md`
- `.specs/phase-11-api-integration-contract/task.md`

## Goal

Menyediakan authenticated read-only API dan canonical JSON export di bawah `/api/v1` yang:

- hanya membaca `published_snapshots`;
- hanya mengembalikan snapshot dengan status `VERIFIED` dan lineage lengkap;
- mempertahankan filing identity, revision, snapshot history, precision, provenance, dan limitations;
- menggunakan representasi dokumen yang sama untuk API detail dan JSON export;
- dapat dikonsumsi HISSA Core melalui schema, OpenAPI, fixtures, dan mock compatibility client.

External contract dimulai dari:

```text
contract: hissa.financial-data.integration
contract_version: 0.1.0
api_version: v1
```

Contract `0.1.0` belum boleh dianggap production-ready sebelum Contract Freeze terpenuhi.

## Scope

### API endpoints

| Method | Endpoint | Behavior |
|---|---|---|
| `GET` | `/api/v1/filings/{filing_id}` | Mengembalikan published snapshot terbaru untuk filing identity |
| `GET` | `/api/v1/snapshots/{snapshot_id}` | Mengembalikan historical snapshot secara exact |
| `GET` | `/api/v1/issuers/{issuer_code}/filings` | Mengembalikan latest published snapshot per filing dengan cursor pagination |
| `GET` | `/api/v1/filings/{filing_id}/export` | Mengunduh canonical JSON document |

Semua endpoint wajib:

- terdaftar melalui `routes/api.php`;
- menggunakan prefix `/api/v1`;
- menggunakan authentication middleware;
- menggunakan rate limiter `hissa-integration`;
- tidak menyediakan POST, PUT, PATCH, atau DELETE;
- tidak memiliki alias unversioned seperti `/api/filings/...`.

### Published document

Successful detail, snapshot, dan export response wajib memuat:

- `api_version`
- `contract`
- `contract_version`
- `snapshot`
- `filing`
- `quality`
- `facts`
- `provenance`
- `limitations`

`facts` wajib mempertahankan seluruh field external contract, termasuk:

- `normalized_fact_id`
- `raw_fact_id`
- `canonical_concept`
- `value`
- `currency`
- `scope`
- `period_start`
- `period_end`
- `data_type`
- `mapping_rule_id`
- `mapping_rule_version`
- `normalization_version`
- `validation_status`

Financial dan decimal values wajib dikirim sebagai JSON string. `null` tidak boleh dikonversi menjadi zero, empty string, atau `UNMAPPED`.

### Published-data isolation

- API hanya boleh membaca `published_snapshots`.
- API tidak boleh merakit response dari `normalized_facts`, `validation_results`, `filings`, atau mutable pipeline state pada request time.
- Filing yang belum dipublish harus menghasilkan `404 PUBLISHED_FILING_NOT_FOUND`.
- Snapshot invalid, non-`VERIFIED`, malformed, atau tanpa lineage lengkap harus ditolak dengan `500 INTEGRATION_CONTRACT_VIOLATION`.
- Request GET tidak boleh mengubah published snapshot.
- Existing snapshot tidak boleh di-hydrate ulang dari data mutable.

### Filing dan snapshot history

- `filing_id` merepresentasikan filing revision tertentu.
- `snapshot_id` merepresentasikan immutable publish result.
- Filing endpoint memilih snapshot terbaru dengan ordering `published_at DESC, snapshot_id DESC`.
- Snapshot endpoint selalu mengembalikan snapshot exact yang diminta.
- Snapshot lama tetap dapat diakses setelah reprocess atau filing revision baru.
- `supersedes_filing_id` digunakan untuk menghubungkan revision history.

### Listing dan pagination

Issuer listing wajib:

- hanya mengembalikan snapshot terbaru per `filing_id`;
- mendukung filter `report_type`, `fiscal_year`, `fiscal_period`, `period_end`, dan `published_after`;
- menggunakan opaque signed cursor;
- memiliki default `limit=25`;
- memiliki maximum `limit=100`;
- menggunakan ordering `published_at DESC, snapshot_id DESC`;
- tidak menyertakan full fact arrays;
- tidak menghasilkan duplicate atau skipped row saat cursor traversal.

Cursor wajib terikat pada filter yang digunakan dan ditolak apabila signature atau query hash tidak valid.

### Canonical JSON dan caching

- Detail API dan export wajib menggunakan serializer yang sama.
- Detail dan export untuk snapshot serta contract version yang sama harus semantic-identical.
- Export menggunakan `Content-Type: application/json`.
- Filename export harus aman dan deterministik.
- Response detail, snapshot, dan export wajib mengirim strong `ETag`.
- `ETag` berasal dari canonical JSON bytes.
- Matching `If-None-Match` harus menghasilkan `304` tanpa body.
- Canonical JSON harus deterministic terhadap field order, fact order, ID arrays, decimal strings, null values, dan UTF-8 encoding.

### Error contract

Semua JSON error wajib memiliki:

```json
{
  "api_version": "v1",
  "error": {
    "code": "ERROR_CODE",
    "message": "Stable consumer-facing message.",
    "details": []
  },
  "meta": {
    "request_id": "..."
  }
}
```

Minimal mapping:

| HTTP | Code |
|---|---|
| `401` | `UNAUTHENTICATED` |
| `404` | `PUBLISHED_FILING_NOT_FOUND` |
| `404` | `PUBLISHED_SNAPSHOT_NOT_FOUND` |
| `422` | `INVALID_IDENTIFIER` |
| `422` | `INVALID_QUERY` |
| `429` | `RATE_LIMITED` |
| `500` | `INTEGRATION_CONTRACT_VIOLATION` |
| `500` | `INTERNAL_ERROR` |

Response dan logs tidak boleh membocorkan:

- stack trace;
- SQL atau query bindings;
- raw exception message;
- absolute filesystem path;
- Authorization header atau plaintext token;
- unpublished financial data;
- full request/response payload.

### Authentication dan operational controls

- Menggunakan Bearer token dari environment/configuration.
- Credential comparison wajib timing-safe.
- Credential tidak boleh disimpan atau ditulis ke logs.
- Credential kosong atau invalid menghasilkan response `401` yang sama.
- Integration identity label boleh dicatat, tetapi bukan nilai credential.
- Rate limit default sandbox: `60 requests/minute` per authenticated identity.
- Setiap request memiliki `X-Request-ID`.
- Logs wajib mencatat route, status, latency, request ID, identity label, dan filing/snapshot ID bila tersedia.
- Integration routes disabled by default di luar local/test environment.
- Production enablement tetap blocked sampai keputusan credential ownership, rotation, revocation, transport security, dan network policy disetujui HISSA Core owner.

## Implementation checklist

### Contract artifacts

- [ ] Tambahkan JSON Schema untuk published filing document.
- [ ] Tambahkan JSON Schema untuk issuer list response.
- [ ] Tambahkan JSON Schema untuk error envelope.
- [ ] Tambahkan OpenAPI document dengan empat GET endpoints.
- [ ] Tambahkan valid fixtures.
- [ ] Tambahkan invalid fixtures untuk non-VERIFIED snapshot, numeric financial value, missing lineage, dan invented unmapped fact.
- [ ] Tambahkan contract tests untuk schema, fixtures, field types, enums, ordering, provenance, dan version separation.

### Publish-time snapshot enrichment

- [ ] Extend `FilingPublishPayloadBuilder`.
- [ ] Capture source URL, source hash, dan storage reference.
- [ ] Capture `period_start`, `data_type`, dan `validation_status`.
- [ ] Capture `supersedes_filing_id`.
- [ ] Tambahkan structured `limitations`.
- [ ] Tambahkan `unmapped_concepts`.
- [ ] Implementasikan `PublishLimitationsCollector`.
- [ ] Pastikan limitations diambil dari dataset/rule-set version yang tepat.
- [ ] Pastikan snapshot tetap immutable setelah publish.
- [ ] Extend `PublishContractValidator`.

### Integration domain

- [ ] Implementasikan `PublishedFilingDocument`.
- [ ] Implementasikan `PublishedFilingDocumentFactory`.
- [ ] Implementasikan `PublishedSnapshotSelector`.
- [ ] Implementasikan `IssuerFilingQuery`.
- [ ] Implementasikan `PublishedSnapshotCursor`.
- [ ] Implementasikan `CursorPage`.
- [ ] Tambahkan typed integration exceptions.
- [ ] Tambahkan deterministic sorting dan canonical serialization.
- [ ] Tambahkan SHA-256 ETag dari canonical bytes.
- [ ] Tambahkan generated/property tests untuk decimal precision, ordering, dan serialization stability.

### Security dan HTTP layer

- [ ] Tambahkan `config/integration-api.php`.
- [ ] Update `.env.example` dengan non-secret configuration keys.
- [ ] Implementasikan `AuthenticateHissaIntegration`.
- [ ] Implementasikan `AssignIntegrationRequestId`.
- [ ] Implementasikan `IntegrationErrorResponse`.
- [ ] Register named rate limiter `hissa-integration`.
- [ ] Register API exception rendering.
- [ ] Pastikan semua route memakai middleware yang sama.

### API routes dan controllers

- [ ] Register `routes/api.php` di `bootstrap/app.php`.
- [ ] Tambahkan empat authenticated GET routes.
- [ ] Implementasikan thin controllers.
- [ ] Implementasikan request validation untuk identifier, filters, limit, cursor, dan dates.
- [ ] Implementasikan API resource untuk issuer listing.
- [ ] Pastikan controller tidak menjalankan business/financial semantics.
- [ ] Pastikan tidak ada implicit binding ke arbitrary mutable model rows.

### Export dan conditional requests

- [ ] Implementasikan canonical response path tunggal.
- [ ] Hubungkan detail, exact snapshot, dan export ke serializer yang sama.
- [ ] Tambahkan safe deterministic filename.
- [ ] Tambahkan ETag untuk semua document response.
- [ ] Tambahkan dukungan `If-None-Match` dan `304`.
- [ ] Tambahkan parity tests antara detail dan export.

### Consumer compatibility dan E2E

- [ ] Implementasikan `HissaCoreMockClient`.
- [ ] Gunakan parser yang sama untuk fixtures dan live endpoint.
- [ ] Tambahkan compatibility tests untuk valid dan invalid documents.
- [ ] Tambahkan E2E initial publish.
- [ ] Tambahkan E2E validation isolation.
- [ ] Tambahkan E2E reprocess dan historical snapshot stability.
- [ ] Tambahkan E2E revision/restatement.
- [ ] Gunakan lebih dari satu filing.
- [ ] Jangan melakukan manual database cleanup atau direct database edits dalam E2E.

### Documentation dan handoff

- [ ] Tambahkan integration runbook.
- [ ] Dokumentasikan seluruh endpoint, filters, cursor, auth, ETag, export, errors, decimal/null semantics, revision behavior, limitations, dan troubleshooting.
- [ ] Tambahkan Contract Freeze checklist dengan kolom `Owner`, `Evidence`, `Date`, dan `Status`.
- [ ] Dokumentasikan version promotion `0.x -> 1.0.0`.
- [ ] Dokumentasikan kapan breaking change membutuhkan contract major baru dan `/api/v2`.
- [ ] Verifikasi dokumentasi terhadap route dan configuration aktual.

## Acceptance criteria

- [ ] Seluruh endpoint hanya tersedia di bawah `/api/v1`.
- [ ] Tidak ada endpoint write atau mutation.
- [ ] Successful financial response hanya berasal dari immutable `VERIFIED PublishedSnapshot`.
- [ ] Unpublished, non-VERIFIED, malformed, dan incomplete-lineage snapshot tidak bocor ke consumer.
- [ ] Decimal/monetary values selalu berupa JSON string.
- [ ] `null` dipertahankan sebagai `null`.
- [ ] Detail API dan export semantic-identical serta memiliki ETag yang sama.
- [ ] Historical snapshot tetap byte-identical setelah reprocess atau filing revision baru.
- [ ] Cursor pagination deterministic dan bebas duplicate/skipped rows.
- [ ] Semua error memakai standard error envelope.
- [ ] Tidak ada credential, secret, SQL, path, exception detail, atau unpublished data pada response/log.
- [ ] OpenAPI, JSON Schema, fixtures, contract tests, feature tests, dan E2E tests tersedia.
- [ ] Mock HISSA Core dapat mengonsumsi fixtures dan live sandbox endpoint.
- [ ] API tetap disabled by default di luar local/test.
- [ ] Contract belum dipromosikan ke `1.0.0` sebelum freeze gate disetujui.

## Verification

Jalankan dari `financial-data-engine-sandbox`:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan route:list --path=api/v1 --except-vendor
python -m pytest ../python/xbrl-worker/tests/test_contract_compatibility.py -q
```

Expected:

- seluruh test pass;
- route list berisi tepat empat authenticated GET endpoints;
- tidak ada route Phase 11 di luar `/api/v1`;
- `git diff --check` tidak menemukan whitespace error;
- tidak ada perubahan di luar scope Phase 11.

## Contract Freeze gate

Contract `1.0.0` dan production enablement hanya boleh dilakukan setelah:

- [ ] Semantics `VERIFIED`, `REVIEW_REQUIRED`, `FAILED`, dan `PENDING` disetujui.
- [ ] Blocking/non-blocking behavior untuk limitations dan `UNMAPPED` disetujui.
- [ ] Publish policy dan lineage requirements stabil.
- [ ] Schema, OpenAPI, fixtures, contract tests, integration tests, dan E2E tests lulus.
- [ ] Security dan network decision disetujui.
- [ ] Known limitations dan consumer migration notes terdokumentasi.
- [ ] Full Stack Engineer memberikan sign-off.
- [ ] Data Analyst memberikan sign-off.
- [ ] HISSA Core owner memberikan sign-off.
- [ ] Production deployment enablement disetujui secara terpisah.

## Out of scope

- Direct write dari HISSA Core.
- Publish, approval, review, mapping, validation, atau reprocess melalui API.
- Koneksi ke production HISSA database/infrastructure.
- GraphQL, webhook, streaming, bulk ZIP, atau asynchronous export job.
- UI HISSA Ops.
- Financial metrics atau semantics baru.
- Automatic mapping/correction untuk `UNMAPPED`.
- Historical backfill sebelum Contract Freeze.

## Open decisions

- Apakah production authentication menggunakan rotated Bearer credential atau OAuth2/mTLS?
- Berapa lama stable contract major dan historical snapshots harus dipertahankan?
- Di repository mana consumer-driven compatibility tests akan diletakkan?
- Apakah non-blocking `UNMAPPED` disclosure diperbolehkan pada filing `VERIFIED`?
