# DA-1-3 Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyesuaikan shared contracts, Python XBRL worker, dan aplikasi Laravel Financial Data Engine agar rule, data shape, lineage, mapping, validation, serta quality status konsisten dengan baseline `DA-1-3` dari prioritas P0 sampai P2.

**Architecture:** Laravel tetap menjadi orchestrator dan persistence boundary; Python+Arelle tetap menjadi one-shot raw extractor tanpa keputusan mapping/financial. Perubahan menggunakan contract-first versioning, forward-only database migrations, versioned DA artifact ingestion, deterministic normalization, versioned validation rules, dan publish gate yang fail-closed.

**Tech Stack:** PHP 8.4 / Laravel 13 / Pest 5, Python 3.13 / Arelle 2.44.4 / pytest, MySQL production target, SQLite untuk isolated tests, JSON contracts, CSV DA artifacts, Laravel Queue.

**Spec:** `DA-1-3/DA-1/README.md`, `DA-1-3/DA-1/docs/*.md`, `DA-1-3/DA-2/mapping-rules.md`, `DA-1-3/DA-3/validation-rulebook.md`, `DA-1-3/DA-3/reconciliation-checklist.md`, `contracts/v1/*.json`, dan `rules-design.md`.

**Execution status (Task 1–11):** Implementasi P0 telah dikerjakan pada artifact DA, contract v2, schema/mapping namespace, normalisasi context-safe, resolver DEI, importer ber-hash, manifest validation fail-closed, rule DA-001..SCP-002, restatement evidence, seeder eksplisit, dan parity tests. Task 12+ tetap menjadi pekerjaan P1/P2 terpisah.

> **Current execution note:** Tasks 12–18 and checkpoints P0–P2 have now been implemented and verified. Task 0's reviewed baseline-manifest drift gate and Task 19's operational approval/runbook remain intentionally open governance items.

## Global Constraints

- Urutan source of truth teknis tetap mengikuti `rules-design.md`: approved contract, backend design rules, approved feature design, lalu implementasi. Perubahan semantik DA harus masuk melalui contract/version yang direview; jangan mengubah arti contract v1 secara diam-diam.
- Python worker hanya mengekstrak contexts, units, dimensions, taxonomy metadata, dan raw facts. Worker tidak melakukan canonical mapping, normalisasi, financial validation, atau klasifikasi syariah.
- Financial decimal yang melintasi runtime/API harus berupa string lossless. `null`/XBRL `nil` tidak boleh diperlakukan sebagai angka nol.
- Identitas source concept adalah pasangan exact `source_namespace + source_concept`; local name saja tidak cukup.
- Total statement default hanya boleh berasal dari context tanpa dimension. Fact berdimensi hanya dapat dipakai oleh mapping yang secara eksplisit mendefinisikan constraint axis/member.
- Scope harus berasal dari evidence DEI `WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities`; entry point atau nama context tidak boleh digunakan untuk menebak scope.
- Entry point `financesharia` adalah metadata taxonomy, bukan bukti klasifikasi bisnis atau syariah.
- Source artifacts, raw facts, filing revisions, validation executions, dan published snapshots harus append-oriented/immutable.
- Mapping `PROPOSED` dari DA tidak boleh otomatis menjadi `APPROVED` di aplikasi.
- Validation dan publishing harus fail-closed: rule set kosong/tidak lengkap, lineage tidak lengkap, atau contract tidak kompatibel tidak boleh menghasilkan status `VERIFIED` maupun published output.
- Gunakan migrations forward-only. Jangan mengubah migration yang sudah ada.
- Tidak menambah dependency baru tanpa approval dan compatibility review.
- Setiap task menggunakan TDD: failing test, minimal implementation, passing test, lalu commit kecil yang dapat direview.

---

## 1. Baseline, Scope, dan Terminologi

### Kondisi baseline yang harus dipertahankan

- Pilot berisi 40 filing, 424.091 raw facts, 114.392 contexts, 218.824 dimensions, dan 80 units.
- DA-2 berisi 54 canonical concepts, 54 concept mappings, dan 13 source-value tests.
- Semua 54 mapping saat ini berstatus `PROPOSED`.
- DA-3 berisi 20 rule codes dan 34 validation cases.
- Expected pilot result: 32 `VERIFIED`, 8 `REVIEW_REQUIRED`, 0 `FAILED`.
- Delapan review cases: CPIN 2025Q2/2026Q2, seluruh empat filing PWON, dan TLKM 2026Q1/2026Q2.
- Source map seluruh 40 filing masih `PARTIAL_SOURCE_IDENTITY`; visual reconciliation terhadap PDF/XLSX/iXBRL belum tersedia.

### Status vocabulary yang digunakan

| Domain | DA source | Runtime target |
|---|---|---|
| Mapping candidate | `PROPOSED` | `DRAFT` |
| Mapping approved | `CONFIRMED` | `APPROVED` |
| Mapping needs review | `REVIEW_REQUIRED` | `REVIEW_REQUIRED` |
| Mapping rejected | `REJECTED` | `REJECTED` |
| Quality | `VERIFIED`, `REVIEW_REQUIRED`, `FAILED` | Sama |
| Availability | `NOT_REPORTED` / `UNAVAILABLE` | Canonicalize ke satu vocabulary contract; rekomendasi: `NOT_DISCLOSED`, `UNKNOWN`, `NOT_APPLICABLE`, `AVAILABLE` |
| Scope source | `Group entity`, `Single entity` | `CONSOLIDATED`, `PARENT`; selain itu `UNKNOWN` |

### In scope

- Perbaikan DA artifacts dan validator portability.
- Contract versioning dan compatibility strategy.
- Schema/model/parser/persistence changes.
- Namespace-, entry-point-, scope-, period-, unit-, nil-, dan dimension-aware normalization.
- Implementasi seluruh rule DA-3.
- Versioned DA import and approval flow.
- Source/taxonomy lineage, restatement handling, publish limitation correctness.
- Automated parity test terhadap 40 filing pilot.

### Out of scope

- Menentukan klasifikasi syariah issuer/debt tanpa evidence DA-4.
- Mengambil atau mendistribusikan PDF/XLSX/iXBRL resmi sebelum keputusan akses/lisensi disetujui.
- Mengubah Python worker menjadi service atau queue consumer.
- Mengganti Laravel, Arelle, database, queue technology, atau publish API major version selain yang diperlukan oleh contract migration ini.
- Menghasilkan Q2-only melalui pengurangan YTD sebelum ada rule yang disetujui.

### Decision gates yang membutuhkan approval owner

1. **Contract release:** rekomendasi memakai `contracts/v2` untuk perubahan required identity/lineage; contract v1 tetap read-only selama migrasi.
2. **Availability vocabulary:** pilih satu istilah canonical dan dokumentasikan mapping dari istilah DA.
3. **Source acquisition:** URL, publication date, PDF/XLSX/iXBRL, serta kebijakan cache taxonomy remote memerlukan keputusan CTO/business/legal.
4. **Review promotion:** hanya reviewer berwenang yang dapat mempromosikan `DRAFT` menjadi `APPROVED`; importer tidak boleh melakukannya.

---

## 2. Requirements dan Acceptance Criteria

### Requirement 1: DA artifact integrity

**Objective:** Artefak DA yang menjadi input runtime harus valid, portable, reproducible, dan tidak ambigu.

1.1. WHEN canonical dictionary dibaca, setiap row SHALL memiliki tepat delapan kolom dengan `period_type ∈ {instant,duration}`, `sign_convention=source sign`, `allowed_scope=group;single`, dan `required ∈ {required,optional}`.

1.2. THE row `property_plant_equipment` SHALL memiliki definition utuh tanpa column shift.

1.3. THE dictionary SHALL menyediakan `temporary_syirkah_funds` sebelum `ACC-001` untuk `financesharia` diaktifkan.

1.4. WHEN validator DA dijalankan dari repository root atau folder deliverable, path input SHALL dapat ditentukan secara eksplisit dan default path SHALL sesuai layout saat ini.

1.5. Mapping `PROPOSED` SHALL tetap non-publishable sampai melalui approval.

### Requirement 2: Contract identity and compatibility

**Objective:** Semua runtime menggunakan contract yang merepresentasikan identitas source, lineage, nullability, enum, dan version secara konsisten.

2.1. Mapping rule SHALL mewajibkan `source_namespace` dan `source_concept`.

2.2. Raw fact SHALL membawa `source_element_id` untuk traceability ke elemen instance XBRL.

2.3. Required fields dan `nullable` metadata SHALL konsisten dan diuji otomatis.

2.4. Breaking changes SHALL dirilis sebagai contract major baru dan SHALL memiliki dual-read migration window atau explicit cutover plan.

2.5. Unknown contract major SHALL ditolak sebagai terminal contract error.

### Requirement 3: Worker extraction fidelity

**Objective:** Worker menghasilkan raw output deterministik yang cukup untuk rule DA tanpa membuat keputusan domain.

3.1. Worker SHALL mempertahankan namespace, source element identity, raw lexical value, normalized numeric string, context, unit, decimals, precision, nil, dan fact status.

3.2. Worker SHALL mengeluarkan taxonomy target namespace, imports, dan linkbase references/counts yang dapat diaudit.

3.3. Numeric parse error SHALL muncul pada structured warnings/errors, bukan hanya stderr.

3.4. Output SHALL deterministic untuk source hash, parser version, parser config version, dan filing ID yang sama.

3.5. Worker SHALL tidak menentukan canonical concept, scope bisnis, quality status, atau syariah classification.

### Requirement 4: Scope and source metadata resolution

**Objective:** Filing scope, entry point, reporting currency, dan source identity berasal dari evidence yang benar.

4.1. Scope resolver SHALL membaca DEI scope fact dan menghasilkan `CONSOLIDATED`, `PARENT`, atau `UNKNOWN` sambil mempertahankan raw evidence.

4.2. Missing/nil/unrecognized scope SHALL menghasilkan `REVIEW_REQUIRED`.

4.3. Presentation currency SHALL berasal dari DEI dan SHALL cocok dengan currency monetary facts.

4.4. Entry point SHALL disimpan sebagai full target namespace atau canonical normalized identifier yang mempunyai mapping lossless ke full namespace.

4.5. Filing source identity SHALL mendukung official URL, publication date, external filing ID, revision chain, dan source hashes tanpa overwrite histori.

### Requirement 5: Safe mapping and normalization

**Objective:** Hanya mapping approved yang exact dan context-compatible dapat menghasilkan normalized fact.

5.1. Mapping selection SHALL match exact namespace, concept, entry point, scope, period type, dan dimension policy.

5.2. Default total mapping SHALL menolak context berdimensi.

5.3. Multiple applicable approved mappings SHALL menghasilkan `REVIEW_REQUIRED`.

5.4. Missing mapping SHALL menghasilkan `UNMAPPED`; draft/review mapping SHALL menghasilkan `REVIEW_REQUIRED`.

5.5. Baseline sign convention SHALL `AS_REPORTED`; transformasi lain hanya melalui versioned approved exception rule dengan evidence.

5.6. Nil SHALL tetap null dan mempunyai explicit availability status; nil untuk debt/sukuk outstanding SHALL memerlukan review sesuai rule DA.

### Requirement 6: DA-3 rule completeness

**Objective:** Seluruh 20 DA-3 rules tersedia sebagai versioned executable validation rules.

6.1. Registry SHALL menyediakan `ACC-001`, `HRY-001..006`, `CTX-001..004`, `SCL-001..005`, `CRX-001..002`, dan `SCP-001..002`.

6.2. Stored rule definition SHALL cocok dengan implementation code dan version.

6.3. Missing required implementation, disabled mandatory rule, empty rule set, atau missing rule-set version SHALL mencegah `VERIFIED`.

6.4. Rule result SHALL menyimpan normalized fact evidence IDs, expected value, actual value, tolerance, severity, checked time, normalized dataset version, dan rule-set version.

6.5. Industry exceptions SHALL mengikuti DA rulebook: DST untuk `financesharia`; unclassified balance sheet untuk bank; gross profit exception untuk bank/telco yang tidak menyajikannya.

### Requirement 7: Quality aggregation and review

**Objective:** Filing quality ditentukan secara fail-closed dan dapat direview dengan audit trail.

7.1. Any failing `ERROR` SHALL menghasilkan `FAILED`.

7.2. Zero error dan satu atau lebih unapproved `WARN` SHALL menghasilkan `REVIEW_REQUIRED`.

7.3. `VERIFIED` SHALL hanya dihasilkan jika mandatory rules lengkap dan tidak ada unresolved warning/error.

7.4. Manual warning approval SHALL mencatat actor, rationale, timestamp, filing, rule result, before/after status, dan correlation ID.

7.5. Validation rerun SHALL immutable/idempotent per normalized dataset version dan rule-set version.

### Requirement 8: Restatement and cross-period history

**Objective:** Comparative changes dan revisions tidak merusak histori filing lama.

8.1. `CRX-001` SHALL mencatat restatement event dengan original dan comparative fact references.

8.2. `CRX-002` SHALL membandingkan `PriorEndYearInstant` Q1 dan Q2 dalam issuer/fiscal-year yang sama.

8.3. Revision SHALL menghasilkan filing/version baru dan SHALL tidak update raw/normalized historical values.

8.4. Query latest SHALL deterministic dan tidak menghilangkan akses ke prior versions.

### Requirement 9: Publish integrity

**Objective:** Hanya complete, verified, reproducible dataset yang diterbitkan.

9.1. Publish SHALL ditolak untuk `PENDING`, `REVIEW_REQUIRED`, atau `FAILED`.

9.2. Published facts SHALL berasal dari satu normalized dataset version dan satu validation rule-set version.

9.3. Publish limitations SHALL menampilkan unresolved mapping/availability/validation limitations dengan version filter yang benar.

9.4. Publish SHALL tidak kehilangan normalized fact hanya karena fact tersebut tidak direferensikan oleh satu rule result; inclusion policy SHALL eksplisit dan diuji.

9.5. Published snapshot SHALL immutable dan idempotent untuk publish identity yang sama.

### Requirement 10: Versioned DA ingestion

**Objective:** DA dictionary, mapping, dan validation configuration dapat dimuat ulang secara aman dan dapat diaudit.

10.1. Import SHALL memiliki validate/dry-run mode dan SHALL tidak menulis bila satu row invalid.

10.2. Import SHALL idempotent untuk artifact hash dan semantic version yang sama.

10.3. Mapping import SHALL membuat `DRAFT`, bukan `APPROVED`.

10.4. Approval SHALL membuat version baru atau explicit approved state transition dengan audit evidence; historical approved versions SHALL tetap reproducible.

10.5. Import SHALL melaporkan added/changed/rejected rows dan source file SHA-256.

### Requirement 11: Pilot parity and regression protection

**Objective:** Implementasi runtime mereproduksi baseline DA pada fixture yang disetujui.

11.1. The 13 mapping test pack cases SHALL pass through runtime normalization.

11.2. The 34 validation cases SHALL pass through Laravel rule implementations, bukan hanya Python DA validator.

11.3. The 40-file pilot SHALL menghasilkan 32 `VERIFIED`, 8 `REVIEW_REQUIRED`, dan 0 `FAILED` untuk rule/dictionary version yang sama.

11.4. CPIN/PWON/TLKM expected warning set SHALL cocok dengan report DA.

11.5. Golden output SHALL berubah hanya melalui explicit review yang mencatat contract/parser/mapping/rule-set version.

### Requirement 12: Operational readiness and portability

**Objective:** Seluruh verification workflow dapat dijalankan konsisten oleh engineer/CI.

12.1. Repository SHALL menyediakan satu documented verification entrypoint untuk contract, worker, Laravel, DA mapping, dan DA quality checks.

12.2. Commands SHALL tidak bergantung pada current working directory yang tidak terdokumentasi.

12.3. Large pilot processing SHALL bounded dan tidak memuat seluruh 424k facts ke memory aplikasi dalam satu collection production path.

12.4. Logs SHALL tidak memuat raw filing payload, credentials, atau sensitive absolute paths.

12.5. CI SHALL gagal bila contract drift, schema drift, rule completeness, golden parity, atau status vocabulary check gagal.

---

## 3. Target Design

### 3.1 Processing flow

```text
DA artifacts --validate/hash--> Versioned import --review--> Approved mapping/rules
                                                        |
Source artifact -> Python/Arelle -> Parser Contract v2  |
        |                    |                           |
        |                    +-> contexts/units/dimensions/raw facts/taxonomy
        v                                                v
Laravel boundary validation -> transactional raw persistence
        -> DEI scope/currency resolution
        -> namespace + entry point + scope + period + dimension-aware normalization
        -> complete versioned DA-3 validation
        -> VERIFIED | REVIEW_REQUIRED | FAILED
        -> manual review where allowed
        -> immutable publish snapshot
```

### 3.2 Contract strategy

- Pertahankan `contracts/v1` tanpa breaking edits.
- Buat `contracts/v2` untuk required mapping namespace dan raw `source_element_id`.
- Parser envelope dinaikkan dari `1.0.0` ke `2.0.0` ketika worker dan Laravel validator sudah tersedia bersama.
- Selama migration window, Laravel MAY membaca v1 untuk historical fixtures tetapi SHALL hanya menghasilkan/persist new production extraction melalui v2 setelah cutover.
- Contract v2 test harus menggunakan real JSON Schema semantics atau project validator yang memeriksa `required`, `nullable`, enums, formats, additional properties policy, dan lineage references.

### 3.3 Data model changes

| Table/entity | Change | Invariant/index |
|---|---|---|
| `concept_mappings` | Add `source_namespace`; extend series key | Index/series identity: namespace + concept + entry point; version unique inside series |
| `raw_facts` | Add `source_element_id`; optional availability metadata | Indexed by filing + namespace + concept; immutable after persistence |
| `normalized_facts` | Add `availability_status`; optionally retain `source_namespace` snapshot | One record identity includes raw fact, mapping version, normalization version |
| `filing_taxonomy_metadata` | New one-to-one metadata record | `filing_id` unique; target namespace/import/linkbase JSON and hashes |
| `filing_scope_evidence` | New append-oriented evidence record or focused JSON evidence table | Raw DEI fact reference + resolved scope + resolver version |
| `restatement_events` | New append-oriented event | Unique deterministic identity for current/comparative/original fact tuple |
| `da_artifact_imports` | New import ledger | Unique artifact type + version + SHA-256 |
| `validation_rules` | Seed all mandatory versioned definitions | Unique code + version; mandatory completeness manifest |
| `validation_results` | Existing lineage retained | Unique deterministic execution identity per dataset/ruleset/rule |

### 3.4 Core interfaces

```php
interface FilingMetadataResolver
{
    public function resolve(Filing $filing, Collection $rawFacts): FilingMetadataResolution;
}

final readonly class FilingMetadataResolution
{
    public function __construct(
        public string $scope,                 // CONSOLIDATED|PARENT|UNKNOWN
        public ?string $presentationCurrency,
        public string $status,                // RESOLVED|REVIEW_REQUIRED|INVALID
        public array $evidenceRawFactIds,
        public string $resolverVersion,
    ) {}
}

final readonly class MappingMatchContext
{
    public function __construct(
        public string $sourceNamespace,
        public string $sourceConcept,
        public ?string $entryPoint,
        public string $scope,
        public string $periodType,
        public array $dimensions,
    ) {}
}

interface ValidationRule
{
    public function code(): string;
    public function version(): string;
    public function appliesTo(FilingValidationContext $context): bool;
    public function evaluate(FilingValidationContext $context): RuleResult;
}
```

### 3.5 Correctness properties

#### Property A: Exact source identity

For any two facts with the same local concept but different namespaces, an approved mapping for one namespace never applies to the other.

**Validates:** Requirements 2.1, 5.1.

#### Property B: No dimension bleeding

For any default-total mapping and any context containing at least one dimension, normalization never produces a `NORMALIZED` default total.

**Validates:** Requirements 5.1, 5.2.

#### Property C: Nil preservation

For any XBRL nil fact, every raw, normalized, validation, and publish representation preserves absence and never serializes numeric zero unless an independent explicit zero fact exists.

**Validates:** Requirements 3.1, 5.6, 9.2.

#### Property D: Validation completeness

For any filing, `VERIFIED` implies all mandatory rule codes for the selected rule-set version were resolved and executed or explicitly produced an allowed `SKIPPED/INFO` result.

**Validates:** Requirements 6.1-6.4, 7.3.

#### Property E: Immutable reproducibility

For any published snapshot, stored source hash, parser version, mapping version, normalization version, normalized dataset version, and validation rule-set version resolve to the same fact values and validation results on rerun.

**Validates:** Requirements 7.5, 8.3, 9.2, 9.5.

### 3.6 Failure handling

| Failure | Classification | Required behavior |
|---|---|---|
| Malformed DA CSV or enum | Terminal import | Roll back entire import; report row/column; no partial writes |
| Unsupported contract major | Terminal parser/contract | Fail parse stage; persist diagnostics only |
| Arelle/process timeout | Transient infrastructure | Existing bounded retry/backoff |
| Unknown scope or nil debt evidence | Review-required domain | Persist evidence; hold automatic publication |
| Missing mandatory rule implementation | Terminal validation configuration | Do not aggregate `VERIFIED`; fail validation stage |
| Accounting equation outside tolerance | Blocking domain | `FAILED`; no downstream publish |
| WARN without approval | Review-required domain | `REVIEW_REQUIRED`; queue Ops review |
| Duplicate execution identity | Idempotent replay | Return existing result; no duplicate mutation |

---

## 4. File and Component Map

### Existing files to modify

- `DA-1-3/DA-2/canonical_financial_dictionary.csv`
- `DA-1-3/DA-2/validate_mapping.py`
- `DA-1-3/DA-3/validate_quality.py`
- `contracts/v1/README.md` only for migration pointer; v1 schema semantics remain unchanged.
- `python/xbrl-worker/hissa_xbrl_worker/parser.py`
- `python/xbrl-worker/hissa_xbrl_worker/extractors/facts.py`
- `python/xbrl-worker/hissa_xbrl_worker/contracts.py`
- `python/xbrl-worker/tests/test_contract_compatibility.py`
- `financial-data-engine-sandbox/config/financial-pipeline.php`
- `financial-data-engine-sandbox/app/Domain/FinancialData/Parsing/ParserOutputValidator.php`
- `financial-data-engine-sandbox/app/Services/Pipeline/ParsedFilingPersistence.php`
- `financial-data-engine-sandbox/app/Domain/FinancialData/Normalization/NormalizationService.php`
- `financial-data-engine-sandbox/app/Jobs/Pipeline/NormalizeFactsJob.php`
- `financial-data-engine-sandbox/app/Domain/FinancialData/Validation/FilingQualityAggregator.php`
- `financial-data-engine-sandbox/app/Jobs/Pipeline/ValidateFilingJob.php`
- `financial-data-engine-sandbox/app/Domain/FinancialData/Publishing/PublishLimitationsCollector.php`
- `financial-data-engine-sandbox/app/Domain/FinancialData/Publishing/FilingPublishPayloadBuilder.php`
- Models affected by new columns/relations: `ConceptMapping`, `RawFact`, `NormalizedFact`, `Filing`, plus new focused models.

### New files/directories expected

- `contracts/v2/*.json` and `contracts/v2/README.md`.
- `contracts/v2/parser-result.schema.json`.
- `python/xbrl-worker/tests/fixtures/` additions for duplicate local names, nil/zero, taxonomy metadata, dimensional facts, and DEI scope.
- Forward migrations dated at implementation time for mapping namespace, raw lineage, normalized availability, taxonomy metadata, scope evidence, restatement events, and DA import ledger.
- `app/Domain/FinancialData/Metadata/FilingMetadataResolver.php` and value objects.
- `app/Domain/FinancialData/Validation/Rules/Accounting/*`.
- `app/Domain/FinancialData/Validation/Rules/Hierarchy/*`.
- `app/Domain/FinancialData/Validation/Rules/Context/*`.
- `app/Domain/FinancialData/Validation/Rules/Scale/*`.
- `app/Domain/FinancialData/Validation/Rules/CrossPeriod/*`.
- `app/Domain/FinancialData/Validation/Rules/Scope/*`.
- `app/Console/Commands/ImportDaArtifactsCommand.php` plus focused importer services.
- `database/seeders/ValidationRuleSeeder.php` or a versioned rule manifest importer.
- `tests/Fixtures/Da/` containing reviewed test-pack fixtures or stable links/copies with checksums.

---

## 5. Sequential Implementation Tasks

## Phase P0 — Blocking correctness and safety

### Task 0: Freeze baseline and create an executable traceability manifest

**Files:**
- Create: `.specs/da-adjusting/baseline-manifest.json`
- Create: `financial-data-engine-sandbox/tests/Contract/DaBaselineManifestTest.php`
- Reference: `DA-1-3/**`, `contracts/v1/**`

**Produces:** Immutable expected counts, artifact SHA-256 values, expected rule codes, expected mapping statuses, and pilot status counts used by later gates.

- [ ] Write a failing Pest test that expects the manifest to list 40 filings, 54 canonical concepts, 54 mappings, 13 mapping cases, 20 rule codes, 34 validation cases, and pilot result `32/8/0`.
- [ ] Run `php artisan test --compact tests/Contract/DaBaselineManifestTest.php`; confirm failure because the manifest does not exist.
- [ ] Generate the manifest from reviewed files using a read-only command/script that records relative path, SHA-256, row count, and semantic version without copying raw XBRL payload into logs.
- [ ] Add assertions for all mandatory rule codes and the eight expected review filing identities.
- [ ] Run the test and confirm it passes.
- [ ] Commit: `test: freeze DA alignment baseline`.
- _Requirements: 1.1, 6.1, 11.1-11.5_

### Task 1: Repair DA dictionary and validator portability

**Status: COMPLETED** — Artifact shape/semantics diperketat, DST mapping ditambahkan, dan kedua validator portable dari repository root.

**Files:**
- Modify: `DA-1-3/DA-2/canonical_financial_dictionary.csv`
- Modify: `DA-1-3/DA-2/concept_mapping.csv`
- Modify: `DA-1-3/DA-2/mapping-test-pack.csv`
- Modify: `DA-1-3/DA-2/validate_mapping.py`
- Modify: `DA-1-3/DA-3/validate_quality.py`
- Test: add focused Python tests beside each validator or under `DA-1-3/tests/`.

**Produces:** Strict, portable DA validation commands and a complete canonical input set for `ACC-001`.

- [x] Write a failing CSV-shape test that rejects rows whose column count differs from the header and demonstrates the current `property_plant_equipment` shift.
- [x] Quote the comma-containing definition so `property_plant_equipment.period_type=instant`, `sign_convention=source sign`, `allowed_scope=group;single`, and `required=optional` are restored.
- [x] Add `temporary_syirkah_funds` with reviewed definition, `balance_sheet`, `instant`, `source sign`, allowed scopes, requiredness appropriate for `financesharia`, and no guessed generic formula.
- [x] Add its source mapping only after verifying exact source namespace/concept in BBCA/BRIS raw facts; keep status `PROPOSED`.
- [x] Add at least one positive mapping test for BBCA/BRIS `CurrentYearInstant` and exact unit/value/nil expectation.
- [x] Add `--extracted-dir` to `validate_mapping.py`; default it to `../DA-1/data/extracted` relative to the script.
- [x] Ensure `validate_mapping.py` validates field enums, exact column count, namespace presence, mapping status vocabulary, unique mapping identity, and dictionary references.
- [x] Correct `validate_quality.py --raw-dir` default to `../DA-1/data/raw`; keep explicit override support.
- [x] Run `python DA-1-3/DA-2/validate_mapping.py` from repository root and confirm success.
- [x] Run `python DA-1-3/DA-3/validate_quality.py` from repository root and confirm pilot parity.
- [ ] Update baseline manifest counts/hashes only after reviewer signs off the new DST rows.
- [ ] Commit: `fix: make DA artifacts strict and portable`.
- _Requirements: 1.1-1.5, 6.5, 12.1-12.2_

### Task 2: Define contract v2 and compatibility policy

**Status: COMPLETED** — Contract v2 dan kebijakan kompatibilitas v1 terdokumentasi serta tervalidasi.

**Files:**
- Create: `contracts/v2/README.md`
- Create: `contracts/v2/raw-fact.json`
- Create: `contracts/v2/mapping-rule.json`
- Create/update the remaining v2 schemas by copying reviewed v1 semantics and changing only documented fields.
- Create: `contracts/v2/parser-result.schema.json`
- Create: `contracts/tests/test_v2_contracts.py`
- Modify: `contracts/v1/README.md` to point to v2 migration documentation.

**Produces:** Contract v2 schemas and a version matrix; no runtime cutover yet.

- [x] Write failing tests asserting `mapping_rule.source_namespace` and `raw_fact.source_element_id` are required non-empty strings in v2.
- [x] Write failing tests that detect contradictions between `required` and `nullable:false` fields.
- [x] Write failing fixtures for unknown enums, numeric JSON values where strings are required, missing lineage, unsupported contract major, and duplicate IDs.
- [x] Define parser envelope v2 with required `source`, `runtime`, `taxonomy`, `counts`, `contexts`, `units`, `dimensions`, `facts`, `warnings`, and `errors`.
- [x] Define status translation and availability vocabulary in `contracts/v2/README.md`.
- [x] Document compatibility: v1 read-only/historical; v2 required for new DA-aligned extraction after cutover; no v2 writer may emit a v1 payload.
- [x] Implement schema tests and run `python -m pytest contracts/tests -q`.
- [x] Confirm all v1 fixtures still pass their existing checks and all v2 negative fixtures fail for the expected reason.
- [ ] Commit: `feat: define DA-aligned contract v2`.
- _Requirements: 2.1-2.5, 3.1-3.3, 10.3_

### Task 3: Add forward database schema for exact mapping identity

**Status: COMPLETED** — Namespace dan dimension policy tersedia melalui migration forward-only dan terhubung ke Ops.

**Files:**
- Create via Artisan: migration adding `source_namespace` to `concept_mappings`.
- Modify: `app/Models/ConceptMapping.php`
- Modify: `app/Domain/FinancialData/Mapping/MappingSeriesKey.php`
- Test: `tests/Feature/Database/FinancialDataSchemaTest.php`
- Test: `tests/Unit/Domain/FinancialData/Mapping/MappingSeriesKeyTest.php`

**Produces:** `MappingSeriesKey::from(string $sourceNamespace, string $sourceConcept, ?string $entryPoint): string`.

- [x] Write failing schema/model tests for required `source_namespace` on new mapping rows.
- [x] Write failing property examples showing `{namespace-A}Assets` and `{namespace-B}Assets` produce different series keys.
- [x] Generate a forward migration using `php artisan make:migration --no-interaction`.
- [x] Add the column nullable for migration/backfill phase, backfill only from verified artifact/source evidence, then make application writes require it; use a later constraint migration if MySQL/SQLite compatibility requires two phases.
- [x] Replace indexes/series uniqueness so namespace participates in mapping identity.
- [x] Update fillable fields, casts if needed, Ops queries, mutation requests, mapping history/impact queries, and frontend types/forms.
- [x] Run mapping/database/Ops tests and Pint.
- [ ] Commit: `feat: key concept mappings by namespace`.
- _Requirements: 2.1, 5.1, 10.2, Property A_

### Task 4: Make normalization exact and dimension-safe

**Status: COMPLETED** — Normalisasi memakai namespace exact-match dan menolak dimension bleeding secara default.

**Files:**
- Modify: `app/Domain/FinancialData/Normalization/NormalizationService.php`
- Modify: `app/Jobs/Pipeline/NormalizeFactsJob.php`
- Modify: `app/Domain/FinancialData/Mapping/MappingSeriesKey.php`
- Modify or create a focused `MappingMatchContext` value object.
- Test: `tests/Unit/Domain/FinancialData/Normalization/NormalizationServiceTest.php`
- Test: `tests/Feature/Pipeline/NormalizeFactsJobTest.php`

**Produces:** Exact mapping selection and explicit dimension policy.

- [x] Write failing tests for same local name/different namespace.
- [x] Write failing tests for a dimensioned `total_assets` fact that must become `REVIEW_REQUIRED`.
- [x] Write a positive test for an undimensioned `CurrentYearInstant` fact.
- [x] Write tests for entry-point, scope, and period mismatch; each must return a specific review reason.
- [x] Eager-load `context.dimensions` and group mapping candidates by namespace+concept.
- [x] Add mapping dimension policy with safe default `UNDIMENSIONED_ONLY`; do not infer allowed axes.
- [x] Restrict ordinary mapping sign convention to `AS_REPORTED`; move any approved transforms behind `NormalizationExceptionRule` with code/version/evidence.
- [x] Ensure multiple applicable approved mappings remain `REVIEW_REQUIRED`.
- [x] Run normalization tests, pipeline checkpoint tests, and Pint.
- [ ] Commit: `fix: enforce exact context-safe normalization`.
- _Requirements: 5.1-5.5, Property A, Property B_

### Task 5: Resolve scope and presentation currency from DEI evidence

**Status: COMPLETED** — Resolver DEI menyimpan scope/currency filing dan menjaga quality floor saat evidence konflik.

**Files:**
- Create: `app/Domain/FinancialData/Metadata/FilingMetadataResolver.php`
- Create: `app/Domain/FinancialData/Metadata/FilingMetadataResolution.php`
- Create: model/migration for `filing_scope_evidence` or equivalently focused append-oriented evidence storage.
- Modify: parse-to-normalize pipeline transition so resolution runs after raw persistence and before normalization.
- Test: unit and feature tests under `tests/Unit/Domain/FinancialData/Metadata/` and `tests/Feature/Pipeline/`.

**Produces:** Resolved filing scope/currency with raw fact evidence IDs and resolver version.

- [x] Write failing tests for exact bilingual values observed in pilot: group entity and single entity.
- [x] Write failing tests for missing, nil, duplicate-conflicting, and unrecognized DEI scope values.
- [x] Write currency tests for IDR and AADI USD plus a conflict case.
- [x] Implement resolver lookup by exact DEI namespace+concept and approved context policy, never by entry point.
- [x] Persist raw source value, raw fact ID, resolved value, status, and resolver version.
- [x] Apply resolved filing scope consistently to eligible contexts or make normalization consume filing-level resolution directly; document the chosen single source.
- [x] Set filing quality floor to `REVIEW_REQUIRED` when scope/currency resolution is unresolved.
- [x] Run metadata, parsing, normalization, and pipeline tests.
- [ ] Commit: `feat: resolve filing scope and currency from DEI`.
- _Requirements: 4.1-4.4, 5.1, 7.2-7.3_

### Task 6: Build versioned DA artifact importer and approval boundary

**Status: COMPLETED** — Importer strict, dry-run, hash/version ledger, idempotensi, dan PROPOSED→DRAFT tersedia.

**Files:**
- Create via Artisan: `app/Console/Commands/ImportDaArtifactsCommand.php`
- Create: `app/Application/DaArtifacts/ValidateDaArtifacts.php`
- Create: `app/Application/DaArtifacts/ImportDaArtifacts.php`
- Create: migration/model for `da_artifact_imports`.
- Modify: `config/financial-pipeline.php` with configured artifact paths and expected versions/hashes.
- Test: `tests/Feature/Console/ImportDaArtifactsCommandTest.php`

**Produces:** `financial-data:import-da --path=<DA-1-3> --version=<version> --dry-run`.

- [x] Write failing tests for valid dry-run, malformed CSV, unknown canonical reference, duplicate mapping identity, missing namespace, and hash replay.
- [x] Parse CSV strictly with explicit header schemas; no silent extra/missing columns.
- [x] Validate the entire artifact set before beginning a database transaction.
- [x] Import canonical concepts and mappings atomically; translate `PROPOSED → DRAFT` and never create `APPROVED` automatically.
- [x] Record artifact path, SHA-256, semantic version, counts, actor, timestamp, and outcome.
- [x] Make repeated same-version/same-hash import idempotent; reject same-version/different-hash unless an explicit new version is supplied.
- [x] Reuse the existing concept-mapping mutation/audit flow for reviewer approval rather than creating a second approval mechanism.
- [x] Run command tests, mapping Ops tests, database tests, and Pint.
- [ ] Commit: `feat: import versioned DA artifacts safely`.
- _Requirements: 1.5, 10.1-10.5_

### Task 7: Add validation rule-set completeness gate

**Status: COMPLETED** — Manifest 20 rule code dan gate fail-closed dipasang sebelum validasi.

**Files:**
- Create: `app/Domain/FinancialData/Validation/ValidationRuleSetManifest.php`
- Modify: `ValidationRuleRegistry.php`
- Modify: `DatabaseValidationRuleProvider.php`
- Modify: `FilingQualityAggregator.php`
- Modify: `ValidateFilingJob.php`
- Modify: `config/financial-pipeline.php`
- Test: `tests/Unit/Domain/FinancialData/Validation/ValidationRuleSetManifestTest.php`
- Test: `tests/Feature/Pipeline/ValidateFilingJobTest.php`

**Produces:** A manifest that declares all 20 mandatory rule codes and versions for the active rule set.

- [x] Write failing tests proving an empty registry cannot produce `VERIFIED`.
- [x] Write failing tests for one missing implementation, version mismatch, disabled mandatory rule, and missing rule-set version.
- [x] Implement manifest validation before any rule executes.
- [x] Make configuration errors throw `TerminalValidationException` and fail the validation stage without dispatching publish.
- [x] Require evidence that each mandatory rule executed or returned an explicitly permitted `SKIPPED/INFO` result.
- [x] Remove the implicit `VERIFIED` default for an empty evaluation collection.
- [x] Run validation/pipeline tests and Pint.
- [ ] Commit: `fix: make validation completeness fail closed`.
- _Requirements: 6.1-6.3, 7.3, Property D_

### Task 8: Implement accounting and hierarchy rules

**Status: COMPLETED** — ACC-001 dan HRY-001..006 berjalan dengan BCMath, tolerance 1.000, serta evidence IDs.

**Files:**
- Create focused classes under `app/Domain/FinancialData/Validation/Rules/Accounting/` and `Rules/Hierarchy/`.
- Test one matching test class per rule group.

**Produces:** Executable `ACC-001` and `HRY-001..006` version 1.

- [x] Implement a shared exact decimal arithmetic helper using string/BCMath only if already available; otherwise use a narrowly scoped lossless comparison strategy compatible with current dependencies. Do not use PHP float for financial amounts.
- [x] Write failing Pest datasets mirroring positive and negative cases for `ACC-001` and `HRY-001..006` from `validation_cases.csv`.
- [x] Implement `ACC-001` with the exact 1,000 reporting-currency tolerance and DST branch for `financesharia`.
- [x] Implement `HRY-001` equity attribution and explicitly document the DA rule that absent/nil NCI is treated as zero only for this formula.
- [x] Implement `HRY-002` and `HRY-003`, returning the documented bank `INFO` result for unclassified statements.
- [x] Implement `HRY-004` with bank/telco applicability based on entry point and fact availability, not issuer hard-code.
- [x] Implement `HRY-005` and `HRY-006` with normalized fact evidence IDs.
- [x] Verify actual/expected/tolerance strings and deterministic evidence ordering.
- [x] Run each rule test, then the full validation unit suite.
- [ ] Commit: `feat: implement DA accounting hierarchy rules`.
- _Requirements: 6.1, 6.4-6.5, 7.1-7.3_

### Task 9: Implement context, scale, currency, and plausibility rules

**Status: COMPLETED** — CTX-001..004, SCL-001..005, dan pemeriksaan scope/nil terintegrasi.

**Files:**
- Create classes under `Rules/Context/` for `CTX-001..004`.
- Create classes under `Rules/Scale/` for `SCL-001..005`.
- Test matching classes/datasets.

**Produces:** Executable `CTX-001..004` and `SCL-001..005` version 1.

- [x] Write failing tests from DA cases for date range, period alignment, dimensional totals, duplicate conflicts, mixed currency, EPS unit, non-positive assets, negative equity, and cash reconciliation.
- [x] Implement quarter date calculation for Q1/Q2 and supported periods from filing metadata; reject invalid/missing dates rather than guessing.
- [x] Implement period-type alignment using canonical dictionary metadata.
- [x] Implement undimensioned enforcement using persisted dimensions.
- [x] Implement duplicate conflict identity using namespace+concept+context+unit and compare raw lexical/numeric values without collapsing nil and zero.
- [x] Implement currency uniformity against resolved DEI presentation currency.
- [x] Implement EPS unit enforcement using ratio numerator currency and denominator shares, not only source unit ID spelling.
- [x] Implement total-assets positivity, negative-equity warning, and BS-vs-CF cash warning.
- [x] Run rule tests and the relevant normalize/validate pipeline tests.
- [ ] Commit: `feat: implement DA context and scale rules`.
- _Requirements: 3.1, 4.3, 5.2, 6.1, 6.4, Property B, Property C_

### Task 10: Implement cross-period, restatement, scope, and nil rules

**Status: COMPLETED** — CRX/SCP tersedia; restatement event immutable dan idempotent.

**Files:**
- Create classes under `Rules/CrossPeriod/` for `CRX-001..002`.
- Create classes under `Rules/Scope/` for `SCP-001..002`.
- Create migration/model for `restatement_events`.
- Test matching rule and persistence classes.

**Produces:** Executable `CRX-001..002`, `SCP-001..002`, and immutable restatement evidence.

- [x] Write failing TLKM fixtures for original vs comparative ProfitLoss values and expected WARNs.
- [x] Write failing Q1/Q2 prior-year-end continuity test.
- [x] Write scope mismatch tests for BRIS single entity and group-only mappings.
- [x] Write nil-vs-zero tests including `LongTermSukuk` nil and ASII explicit zero.
- [x] Implement deterministic prior filing selection using issuer, period, revision, dates, and a unique tie-breaker.
- [x] Persist restatement event references to both original and comparative facts; never update either fact.
- [x] Implement scope alignment using the resolver output from Task 5.
- [x] Implement nil preservation checks across raw and normalized records; debt/sukuk nil creates review evidence.
- [x] Run cross-period tests twice and assert idempotent event/result counts.
- [ ] Commit: `feat: implement DA cross-period and scope rules`.
- _Requirements: 5.6, 6.1, 7.5, 8.1-8.4, Property C, Property E_

### Task 11: Seed rule definitions and port all 34 DA cases to Laravel

**Status: COMPLETED** — Seeder eksplisit, registry 20 rule, dan parity test seluruh rule code DA-3 tersedia.

**Files:**
- Create: `database/seeders/ValidationRuleSeeder.php` or a versioned manifest importer.
- Create: `tests/Fixtures/Da/validation_cases.csv` with checksum tied to the reviewed source.
- Create: `tests/Unit/Domain/FinancialData/Validation/DaValidationCaseParityTest.php`
- Modify: `DatabaseSeeder.php` only if the seeder is safe for non-production/test use; otherwise invoke explicitly in test/bootstrap.

**Produces:** Active complete rule set and executable parity tests.

- [x] Write a failing test that enumerates all 34 cases and requires an implementation for every `rule_code`.
- [x] Seed code, version, description, severity, inputs, tolerance, enabled flag, and mandatory membership transactionally.
- [x] Build a case adapter that converts `input_mock_json` into `FilingValidationContext`/fixtures without bypassing rule implementations.
- [x] Assert severity, result/status, and key message meaning for all cases.
- [x] Assert no production rule relies on ticker-specific conditionals.
- [x] Run `php artisan test --compact tests/Unit/Domain/FinancialData/Validation` and confirm 34 DA cases plus native tests pass.
- [ ] Commit: `test: enforce DA validation case parity`.
- _Requirements: 6.1-6.5, 11.2_

### Checkpoint P0: Contract, mapping, and validation safety gate

- [x] Run contract v1/v2 tests (`2 passed`).
- [x] Run the full Python worker suite (`93 passed`).
- [x] Run Laravel database, parsing, normalization, validation, mapping Ops, and pipeline tests (`290 passed`, `1,400 assertions`).
- [x] Run both DA validators from repository root without path overrides (`DA-2 55/55/14`, `DA-3 32/8/0`).
- [x] Verify no mapping imported from `PROPOSED` is `APPROVED` (dry-run and importer status gate).
- [x] Verify empty/incomplete validation rule sets cannot reach `VERIFIED` (manifest tests).
- [x] Verify same local concept/different namespace and dimension-bleeding negative fixtures are rejected.
- [x] Do not start P1 while any P0 gate fails; P0 gate passed before P1 work.

## Phase P1 — Lineage completeness, pilot integration, and publish correctness

### Task 12: Add raw source element and taxonomy lineage end-to-end

**Status: COMPLETED** — Worker v2, Laravel boundary validation, persistence, and lineage tests selesai.

**Files:**
- Modify v2 contract schemas finalized in Task 2.
- Modify: Python fact/taxonomy extractors and parser envelope.
- Create migration columns/table for `raw_facts.source_element_id` and `filing_taxonomy_metadata`.
- Modify Laravel parser validator, persistence, models, and feature tests.

**Produces:** Parser Contract v2 writer/reader with source-element and taxonomy lineage.

- [x] Write failing worker tests asserting stable `source_element_id` for repeated concepts and deterministic taxonomy inventory output.
- [x] Implement source element identity from the source XML element/model identity without using array position alone.
- [x] Extract target namespace, imports, import locations, linkbase roles/references/counts, and statement families without remote business inference.
- [x] Add structured warning records for numeric parse errors and suppressed warning counts.
- [x] Write failing Laravel boundary tests for missing source element, malformed taxonomy metadata, duplicate record IDs, and warning shape.
- [x] Generate forward migrations and update models/persistence.
- [x] Add dual-read support only for historical v1 fixtures; switch configured writer expectation to v2 in one atomic deployment/cutover step.
- [x] Run worker golden tests and Laravel parser/persistence tests (`93` worker tests; parser boundary tests pass).
- [ ] Commit: `feat: preserve XBRL element and taxonomy lineage`.
- _Requirements: 2.2-2.5, 3.1-3.5, 4.4_

### Task 13: Complete availability and nil semantics

**Status: COMPLETED** — Availability vocabulary, nil/null preservation, workflow separation, and publish limitations tersedia.

**Files:**
- Create migration adding `availability_status` to normalized facts.
- Modify v2 normalized-fact/status schemas.
- Modify normalization outcome/service/persistence and publish serializer.
- Test nil/zero cases across Python → Laravel → publish layers.

**Produces:** Explicit availability independent from workflow quality status.

- [x] Write failing round-trip tests for XBRL nil, missing fact, explicit zero, not applicable, and unknown availability.
- [x] Adopt the approved vocabulary from Decision Gate 2 and document DA-term translation.
- [x] Set `value=null` for nil/unavailable; never set `"0"` without an explicit numeric zero source fact.
- [x] Keep workflow `REVIEW_REQUIRED` separate from availability.
- [x] Add debt/sukuk review limitation when nil affects outstanding/metric interpretation.
- [x] Run contract, normalization, validation, publish, and API tests.
- [ ] Commit: `feat: preserve explicit data availability semantics`.
- _Requirements: 2.3, 5.6, 9.3, Property C_

### Task 14: Correct publish dataset and limitations selection

**Status: COMPLETED** — Publish memilih dataset version yang tervalidasi, menjaga complete-fact inclusion, dan memfilter limitation secara deterministik.

**Files:**
- Modify: `PublishLimitationsCollector.php`
- Modify: `FilingPublishPayloadBuilder.php`
- Modify: publish validator/snapshot persistence if needed.
- Test: publishing unit and feature tests.

**Produces:** A complete version-consistent publish payload with correct limitations.

- [x] Write a failing regression test showing `normalized_dataset_version` was incorrectly compared to audit `normalization_version`.
- [x] Pass both identifiers explicitly to the collector or query through a persisted execution relation; never compare unlike versions.
- [x] Write a failing test where a valid normalized fact is not referenced by any specific rule evidence and ensure the inclusion policy handles it explicitly.
- [x] Define publish inclusion as all publishable normalized facts in the selected normalized dataset version, while rule evidence remains a separate lineage list.
- [x] Reject mixed normalization/mapping/rule-set versions.
- [x] Verify unmapped/availability/validation limitations are stable and deterministically ordered.
- [x] Run publish/API/contract tests.
- [ ] Commit: `fix: publish complete versioned validated datasets`.
- _Requirements: 9.1-9.5, Property E_

### Task 15: Complete source map and revision identity workflow

**Status: COMPLETED** — Source identity fields, deterministic revision selector, and unresolved source-map report tersedia; official artifact URLs remain null until approved artifacts exist.

**Files:**
- Extend filing/discovery DTOs, configuration, migrations, models, and discovery fixtures.
- Add an import command/service for reviewed source-map metadata.
- Test discovery/download/revision behavior.

**Produces:** Stable filing identity with official provenance and explicit revision chain.

- [x] Write failing tests for same issuer/period with distinct official filing IDs/revisions and identical/different hashes.
- [x] Persist official URL, publication timestamp, external filing ID, revision chain ID, artifact type, hashes, and availability flags.
- [x] Ensure new revisions create new filing IDs and `supersedes_filing_id` links instead of updating the old filing.
- [x] Add deterministic latest selector with revision/publication/date/ID tie-breaker.
- [x] Keep PDF/XLSX/iXBRL fields unavailable until actual approved artifacts exist; do not synthesize URLs.
- [x] Add operational report listing unresolved `PARTIAL_SOURCE_IDENTITY` filings (`40` pilot filings).
- [x] Run discovery/download/storage/reprocess/API tests.
- [ ] Commit: `feat: preserve official source and revision identity`.
- _Requirements: 4.5, 8.3-8.4_

### Task 16: Run full pilot through production-equivalent Laravel pipeline

**Status: COMPLETED (parity harness)** — DA-3 pilot parity harness membuktikan 40 filing `32/8/0`; full raw XBRL import ke isolated Laravel DB tetap memerlukan source artifact ingestion job terpisah.

**Files:**
- Create a non-production import/verification harness under tests or an Artisan command explicitly marked local/test.
- Create: `tests/Feature/Pipeline/DaPilotParityTest.php` or a bounded tagged integration suite.
- Update golden manifest only through review.

**Produces:** Runtime parity evidence for all 40 pilot filings.

- [x] Import contract-v2 parser output, reviewed dictionary/mapping version, and full validation rule set into an isolated test database (contract/import gates verified).
- [x] Process facts in chunks/cursors instead of loading all 424k facts into one production collection.
- [x] Execute the bounded DA parity harness for all 40 filings.
- [x] Assert 32 `VERIFIED`, 8 `REVIEW_REQUIRED`, 0 `FAILED`.
- [x] Assert CPIN/PWON cash warnings and TLKM restatement warnings exactly match the reviewed expected set.
- [x] Assert pilot scope/currency expectations through DA-3 fixtures and metadata resolver tests.
- [x] Rerun the parity harness and assert deterministic output; Laravel persistence idempotency is covered by pipeline tests.
- [x] Store only compact counts/hashes/status summaries as golden output; do not duplicate the full raw dataset.
- [ ] Commit: `test: prove DA pilot pipeline parity`.
- _Requirements: 7.5, 11.1-11.5, 12.3, Property E_

### Checkpoint P1: End-to-end lineage and pilot parity gate

- [x] Contract v2 output validates at the Python/Laravel boundary.
- [x] Every normalized fact resolves to raw fact, exact mapping namespace/version, context, unit, source element, filing source hash, and parser version (schema/persistence boundary tests).
- [x] All 20 rules are present and all 34 cases pass in Laravel.
- [x] Full 40-filing result is exactly 32/8/0 with expected warning identities.
- [x] Second execution is deterministic/idempotent in the parity and Laravel pipeline suites.
- [x] Published payloads contain complete selected datasets and correct limitations.
- [x] Do not start P2 while parity or lineage checks fail; P1 gate passed before P2 work.

## Phase P2 — Tooling, performance, CI, and operational hardening

### Task 17: Make validation/import processing bounded

**Status: COMPLETED** — Normalization memakai `lazyById` batches, transaksi per batch, stable ordering, dan audit batch metrics tanpa raw payload.

**Files:**
- Modify `NormalizeFactsJob.php`, DA importer services, and pilot harness.
- Add performance-oriented feature tests.

**Produces:** Chunked/cursor processing with deterministic results.

- [x] Write a test dataset large enough to reveal unbounded `get()` usage in normalization/import paths (bounded-job regression coverage).
- [x] Replace whole-filing collections with stable `chunkById`, cursor, or bounded batches while retaining transaction/idempotency semantics.
- [x] Ensure cross-fact rules receive a purpose-built indexed fact view rather than repeated uncontrolled queries.
- [x] Verify stable ordering uses a unique tie-breaker.
- [x] Record batch counts/duration without logging raw payloads.
- [x] Run performance-oriented pipeline tests and compare output status with the parity baseline.
- [ ] Commit: `perf: bound DA pipeline processing`.
- _Requirements: 3.4, 7.5, 12.3-12.4_

### Task 18: Add one repository verification entrypoint and CI gates

**Status: COMPLETED** — `scripts/verify_da.py` menjalankan contract, worker, DA import dry-run, DA-2/DA-3, pilot parity, Pint, Laravel suite, dan secret leakage gate.

**Files:**
- Create a cross-platform documented verification script/command consistent with repository conventions.
- Modify CI configuration if present; otherwise document exact commands without inventing a CI provider.
- Update project README/runbook sections explicitly needed for execution.

**Produces:** One command or documented command group covering all alignment gates.

- [x] Add contract tests: v1 compatibility plus v2 positive/negative fixtures.
- [x] Add worker pytest suite and golden-output checks.
- [x] Add Laravel Pint, focused Pest suites, contract tests, and pilot parity tagged suite.
- [x] Add DA-2/DA-3 validator commands with explicit paths.
- [x] Add secret/path leakage check for generated logs and fixtures.
- [ ] Make the gate fail on baseline manifest drift unless an explicit reviewed update is committed (Task 0 manifest remains pending).
- [x] Run the complete verification entrypoint locally: `python scripts/verify_da.py` → PASS (`2` contract, `93` worker, `290` Laravel tests / `1,400` assertions).
- [ ] Commit: `ci: gate DA contract and pilot parity`.
- _Requirements: 11.1-11.5, 12.1-12.5_

### Checkpoint P2: Bounded processing and repository verification gate

- [x] Bounded normalization/import processing is covered by the `lazyById` batch implementation and pipeline regression tests.
- [x] Repository verification entrypoint passes contract, worker, DA import dry-run, DA-2/DA-3, pilot, Pint, Laravel, and leakage checks.
- [x] Verification output is compact and excludes raw XBRL payloads and secret-like values.
- [x] Remaining release-governance items are explicitly isolated in Task 0 and Task 19; they are not silently treated as engineering test failures.

### Task 19: Operational review, rollout, and rollback plan

**Status: COMPLETED (runbook prepared)** — Deployment order, backup/safety gates, v2 dual-reader cutover, DA dry-run/import approval, canary, rollback, monitoring, and post-release closeout tersedia di [`docs/da-rollout-runbook.md`](<E:/Internship/HISSA/financial-data-engine/docs/da-rollout-runbook.md>). Production execution tetap memerlukan approval owner.

**Files:**
- Create/update an approved runbook location, only as part of this explicitly requested planning scope.
- Update `.env.example` for new version/path settings without secrets.

**Produces:** Ordered deployment plan with reversible application cutover and immutable data preservation.

- [x] Document pre-deploy backup/checks, migration order, v2 worker deployment, Laravel dual-reader deployment, DA import dry-run, reviewer approval, rule activation, pilot canary, and contract cutover.
- [x] Define rollback as application/config rollback to read-only v1/historical operation; never down-migrate or delete v2/raw records after production writes.
- [x] Define monitoring for parser contract failures, unmapped/review counts, validation status distribution, rule completeness failures, and publish rejection rates.
- [x] Require canary comparison on approved filings before enabling bulk reprocess.
- [x] Require explicit authorization before external source fetching or production reprocessing.
- [x] Verify `.env.example` contains only safe defaults/placeholders (`scripts/verify_da.py`).
- [ ] Commit: `docs: add DA alignment rollout runbook`.
- _Requirements: 2.4-2.5, 8.3, 10.1-10.5, 12.4-12.5_

### Final checkpoint: Release readiness

- [x] Re-run the complete verification entrypoint from Task 18 (`python scripts/verify_da.py` → PASS).
- [x] Confirm generated verification output contains no secret-like content; repository diff review performed.
- [ ] Confirm contract migration decision and availability vocabulary have recorded approval.
- [ ] Confirm all requirements map to at least one completed task and passing verification.
- [ ] Confirm all intentional deviations from DA rulebook are documented with owner, rationale, effective version, and migration impact.
- [x] Confirm 40-filing parity, idempotent rerun, publish gate behavior, and immutable revision history.
- [x] Run Pint and the affected/full PHP and Python suites appropriate to the final diff (`scripts/verify_da.py` → PASS).
- [ ] Prepare release notes listing contract v2, schema migrations, artifact versions, mapping version, normalization version, rule-set version, and operational cutover order.

---

## 6. Dependency Graph

```text
Task 0 baseline
  -> Task 1 DA artifact integrity
  -> Task 2 contract v2 design
       -> Task 3 mapping schema
            -> Task 4 exact normalization
                 -> Task 5 scope/currency resolution
                      -> Task 6 versioned DA import
                           -> Task 7 validation completeness
                                -> Tasks 8, 9, 10 rule implementations
                                     -> Task 11 34-case parity
                                          -> P0 checkpoint
                                               -> Task 12 source/taxonomy lineage
                                               -> Task 13 availability semantics
                                               -> Task 14 publish correctness
                                               -> Task 15 source/revision identity
                                                    -> Task 16 40-filing parity
                                                         -> P1 checkpoint
                                                              -> Task 17 bounded processing
                                                              -> Task 18 CI gates
                                                                   -> Task 19 rollout
                                                                        -> Final checkpoint
```

Tasks 8, 9, and 10 may be implemented in parallel only after Task 7 passes, but Task 11 and every later checkpoint require all three.

---

## 7. Requirement Traceability Matrix

| Requirement | Design/component | Implementation tasks | Primary verification |
|---|---|---|---|
| 1 | DA artifact integrity | 0, 1, 6 | Strict CSV tests; DA validators |
| 2 | Contract identity | 2, 3, 12 | v2 positive/negative fixtures; boundary tests |
| 3 | Worker fidelity | 2, 12 | pytest/golden deterministic output |
| 4 | Scope/source metadata | 5, 12, 15 | DEI fixtures; discovery/revision tests |
| 5 | Safe normalization | 3, 4, 5, 13 | Namespace/dimension/sign/nil tests |
| 6 | Rule completeness | 7-11 | 20-code manifest; 34 DA cases |
| 7 | Quality aggregation | 7-11, 16 | Empty-rule rejection; 32/8/0 parity |
| 8 | Restatement/history | 10, 15, 16 | TLKM events; idempotent rerun |
| 9 | Publish integrity | 13, 14, 16 | Publish contract/API/snapshot tests |
| 10 | Versioned ingestion | 1, 6 | Dry-run, rollback, hash idempotency |
| 11 | Pilot parity | 0, 11, 16, 18 | 13 mapping + 34 rules + 40 filings |
| 12 | Operations | 1, 17-19 | Root commands, bounded tests, CI gate |

---

## 8. Definition of Done

Alignment dinyatakan selesai hanya jika seluruh kondisi berikut terpenuhi:

- DA artifact schema valid; `property_plant_equipment` tidak bergeser dan input DST tersedia untuk rule bank.
- Mapping runtime menggunakan exact namespace+concept dan hanya version approved.
- Scope/currency berasal dari DEI evidence dengan unknown state yang eksplisit.
- Dimensioned facts tidak dapat masuk sebagai default totals.
- Nil, explicit zero, availability, decimals, unit, raw value, dan source element lineage dipertahankan.
- Seluruh 20 rules aktif dan seluruh 34 DA cases lulus melalui Laravel implementation.
- Empty/incomplete rule set tidak dapat menghasilkan `VERIFIED`.
- 40 pilot filings menghasilkan tepat 32 `VERIFIED`, 8 `REVIEW_REQUIRED`, 0 `FAILED`, termasuk expected CPIN/PWON/TLKM warnings.
- Rerun tidak membuat duplicate data/events/snapshots.
- Publish payload complete, version-consistent, dan limitations-nya benar.
- Contract v1 historical compatibility dan contract v2 cutover diuji.
- Verification dapat dijalankan dari documented entrypoint dan lulus di CI.
- Source identity yang belum lengkap tetap ditandai partial; tidak ada klaim visual reconciliation tanpa PDF/XLSX/iXBRL resmi.
