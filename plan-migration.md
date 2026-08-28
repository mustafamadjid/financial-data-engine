# HISSA Financial Data Engine — Database Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat Laravel migrations untuk schema database HISSA Financial Data Engine yang mengikuti `contracts/v1` sebagai source of truth logical, menjaga nama field, semantics, nullability, enum, lineage, dan versioning secara konsisten.

**Architecture:** Gunakan pendekatan **contract-first persistence**. Field scalar pada contract dipertahankan dengan nama yang sama di database sejauh memungkinkan. Database boleh memakai tipe fisik yang lebih tepat untuk MySQL (misalnya `DECIMAL` untuk financial number yang dikirim sebagai JSON string), tetapi serializer di layer aplikasi nantinya harus tetap dapat menghasilkan contract JSON v1 tanpa kehilangan informasi. Array-reference pada contract v1 disimpan sebagai kolom JSON terlebih dahulu agar struktur persistence tetap dekat dengan contract; normalisasi ke pivot table hanya dilakukan pada versi berikutnya jika benar-benar diperlukan.

**Tech Stack:** PHP `^8.3`, Laravel `^13.17`, MySQL, Laravel Schema Builder, Pest `^5.1`.

**Spec / Source of Truth:**
- `contracts/v1/README.md`
- `contracts/v1/filing-metadata.json`
- `contracts/v1/context.json`
- `contracts/v1/unit.json`
- `contracts/v1/dimension.json`
- `contracts/v1/raw-fact.json`
- `contracts/v1/mapping-rule.json`
- `contracts/v1/normalized-fact.json`
- `contracts/v1/validation-result.json`
- `contracts/v1/metric.json`
- `contracts/v1/debt-record.json`
- `contracts/v1/evidence-record.json`
- `contracts/v1/status-enums.json`
- `HISSA_Financial_Data_Engine_Internship_Work_Brief_v1.1.pdf`

## Global Constraints

- Work only inside `financial-data-engine-sandbox/`.
- Read and follow `financial-data-engine-sandbox/AGENTS.md` before changing application code.
- Current repository uses Laravel `^13.17` and PHP `^8.3`; do not downgrade the framework to match older planning documents.
- Database target is MySQL as configured by `.env.example`.
- `contracts/v1` is the logical source of truth for field names, enum values, null semantics, and lineage.
- Do not silently rename contract fields in database migrations.
- Do not silently change enum vocabulary.
- `nullable: false` in a contract maps to a `NOT NULL` database column unless this plan explicitly identifies a contract blocker.
- `nullable: true` maps to a nullable database column.
- Contract `required` controls payload validation; database nullability is controlled by `nullable`.
- Monetary/decimal values that are serialized as JSON strings must use MySQL `DECIMAL`, not `FLOAT` or `DOUBLE`, whenever they represent numeric financial values.
- `raw_value` must preserve source lexical content and must not be coerced into a numeric DB type.
- Raw source and raw facts are immutable at the domain level. Migrations must not add cascade behavior that would casually destroy raw lineage.
- Revision/restatement must create new filing identity; no overwrite semantics.
- Stable contract identifiers remain strings. Do not replace them with auto-increment IDs as the only identity exposed to domain data.
- Timestamps added by Laravel (`created_at`, `updated_at`) are persistence metadata and are allowed even though they are not part of the JSON contracts.
- For v1, array fields in contracts (`allowed_scope`, `normalized_fact_ids`, `input_fact_ids`, `evidence_ids`) are persisted as JSON to preserve structural parity. Do not introduce pivot tables in this migration plan.
- Do not create Eloquent models, services, controllers, queue jobs, parser integration, or API resources in this plan.
- Migrations must be fully reversible with `down()`.
- Every migration task must include schema tests before implementation.
- Use `php artisan test` and `php artisan migrate:fresh` as verification gates.
- Run Laravel Pint on changed PHP files before final completion.

---

# 1. Design Decision: Strict Contract Parity for v1

For this migration phase, database field naming must stay intentionally close to the contract.

```text
Contract field                  Database column
------------------------------------------------------------
filing_id                       filing_id
issuer_code                     issuer_code
source_concept                  source_concept
source_namespace                source_namespace
raw_value                       raw_value
normalized_numeric_value        normalized_numeric_value
context_ref                     context_ref
unit_ref                        unit_ref
canonical_concept               canonical_concept
mapping_rule_id                 mapping_rule_id
mapping_rule_version            mapping_rule_version
normalization_status            normalization_status
validation_status               validation_status
normalized_fact_ids             normalized_fact_ids (JSON)
input_fact_ids                  input_fact_ids (JSON)
evidence_ids                    evidence_ids (JSON)
```

Do **not** translate:

```text
context_ref -> context_id
unit_ref -> unit_id
canonical_concept -> canonical_concept_id
```

during v1.

The reason is to reduce translation layers while the pilot data contract is still being stabilized.

Foreign keys may still reference stable string identifiers:

```text
raw_facts.context_ref
    -> xbrl_contexts.context_id

raw_facts.unit_ref
    -> xbrl_units.unit_id
```

This maintains both contract naming and relational integrity.

---

# 2. Known Contract Risks That Must Not Be Silently Fixed

## 2.1 `normalized_fact` and `UNMAPPED`

Current contract requires non-null:

```text
canonical_concept
mapping_rule_id
```

while `normalization_status` permits:

```text
UNMAPPED
```

Those semantics conflict if an unmapped fact is intended to be stored as a `normalized_fact`.

**Migration rule for v1:**

- Follow the contract literally.
- Keep `canonical_concept` and `mapping_rule_id` non-null.
- Do not invent placeholder values such as `UNKNOWN` or fake mapping IDs.
- The normalization implementation must not create a `normalized_facts` row for an unmapped fact until the contract is revised.
- Unmapped state remains a later application-workflow concern.

This plan must not modify the contract.

## 2.2 `required` vs `nullable: false`

Some contract properties are non-nullable but are not listed in `required`.

Database rule:

```text
nullable=false -> NOT NULL
nullable=true  -> NULL
```

Do not infer DB nullability from the `required` array.

## 2.3 Multiple source artifacts

Current `filing_metadata` embeds:

```text
source_url
source_type
storage_path
source_hash
```

and therefore logically models one primary source artifact per filing.

The Phase 3 work plan mentions `filing_artifacts`, but there is no `filing_artifact` contract in `contracts/v1`.

**Decision for v1 migrations:**

- Do not create `filing_artifacts` yet.
- Store the source fields directly on `filings`.
- Multiple-artifact persistence requires a future contract revision or a dedicated `filing_artifact` contract.

## 2.4 Debt master normalization

Phase 3 mentions:

```text
creditors
creditor_aliases
debt_facilities
```

but current `debt_record` contract stores `borrower`, `creditor`, and `facility` directly as strings.

**Decision for v1 migrations:**

- Implement `debt_records` exactly from the contract.
- Implement `evidence_records`.
- Do not add creditor/facility master tables until DA-4 semantics are stable and a contract supports them.

---

# 3. Migration File Order

Create migrations in this dependency order. The exact timestamp prefix is generated by Artisan.

```text
create_filings_table
create_xbrl_contexts_table
create_xbrl_units_table
create_xbrl_dimensions_table
create_raw_facts_table
create_canonical_concepts_table
create_concept_mappings_table
create_normalized_facts_table
create_validation_rules_table
create_validation_results_table
create_financial_metrics_table
create_evidence_records_table
create_debt_records_table
create_audit_logs_table
```

---

# 4. Database Type Mapping Rules

| Contract type/semantic | MySQL/Laravel migration type |
|---|---|
| Stable ID string | `$table->string(..., 128)` |
| Short code | `$table->string(..., 100)` |
| Enum/state | `$table->string(..., 50)` |
| Free-form description/message/rationale | `$table->text(...)` |
| URL | `$table->text(...)` |
| Storage path | `$table->text(...)` |
| SHA-256/source hash | `$table->string(..., 128)` |
| Date | `$table->date(...)` |
| Date-time | `$table->dateTimeTz(...)` |
| Boolean | `$table->boolean(...)` |
| Integer version | `$table->unsignedInteger(...)` |
| Financial decimal | `$table->decimal(..., 65, 18)` |
| Raw lexical XBRL value | `$table->longText(...)` |
| Array/object | `$table->json(...)` |

Do not use `float()` or `double()` for financial values.

---

# 5. Indexing Rules

Required indexes:

```text
filings:
- PRIMARY filing_id
- INDEX issuer_code
- INDEX period_end
- INDEX (issuer_code, fiscal_year, fiscal_period)
- INDEX source_hash
- INDEX supersedes_filing_id

xbrl_contexts:
- PRIMARY context_id
- INDEX filing_id
- UNIQUE (filing_id, source_context_id)

xbrl_units:
- PRIMARY unit_id
- INDEX filing_id
- UNIQUE (filing_id, source_unit_id)

xbrl_dimensions:
- PRIMARY dimension_id
- INDEX context_id
- INDEX axis

raw_facts:
- PRIMARY raw_fact_id
- INDEX filing_id
- INDEX source_concept
- INDEX context_ref
- INDEX unit_ref
- INDEX fact_status

canonical_concepts:
- PRIMARY code

concept_mappings:
- PRIMARY mapping_rule_id
- INDEX source_concept
- INDEX canonical_concept
- INDEX status
- INDEX (source_concept, entry_point, rule_version)

normalized_facts:
- PRIMARY normalized_fact_id
- INDEX filing_id
- INDEX raw_fact_id
- INDEX canonical_concept
- INDEX mapping_rule_id
- INDEX normalization_status
- INDEX validation_status

validation_rules:
- UNIQUE (rule_code, rule_version)
- INDEX enabled
- INDEX severity

validation_results:
- PRIMARY validation_result_id
- INDEX filing_id
- INDEX (rule_code, rule_version)
- INDEX result
- INDEX severity

financial_metrics:
- PRIMARY metric_id
- INDEX filing_id
- INDEX issuer_code
- INDEX metric_code
- INDEX (issuer_code, period, metric_code, formula_version)

evidence_records:
- PRIMARY evidence_id
- INDEX filing_id
- INDEX extraction_method

debt_records:
- PRIMARY debt_record_id
- INDEX filing_id
- INDEX classification
- INDEX classification_status

audit_logs:
- INDEX actor_id
- INDEX action
- INDEX entity_type
- INDEX entity_id
- INDEX filing_id
- INDEX created_at
```

Do not create speculative indexes beyond this set during v1.

---

# 6. Foreign Key and Delete Policy

Use conservative delete behavior because lineage and auditability matter.

```text
xbrl_contexts.filing_id -> filings.filing_id ON DELETE RESTRICT
xbrl_units.filing_id -> filings.filing_id ON DELETE RESTRICT
raw_facts.filing_id -> filings.filing_id ON DELETE RESTRICT
raw_facts.context_ref -> xbrl_contexts.context_id ON DELETE RESTRICT
raw_facts.unit_ref -> xbrl_units.unit_id ON DELETE RESTRICT
xbrl_dimensions.context_id -> xbrl_contexts.context_id ON DELETE RESTRICT
concept_mappings.canonical_concept -> canonical_concepts.code ON DELETE RESTRICT
normalized_facts.filing_id -> filings.filing_id ON DELETE RESTRICT
normalized_facts.raw_fact_id -> raw_facts.raw_fact_id ON DELETE RESTRICT
normalized_facts.canonical_concept -> canonical_concepts.code ON DELETE RESTRICT
normalized_facts.mapping_rule_id -> concept_mappings.mapping_rule_id ON DELETE RESTRICT
validation_results.filing_id -> filings.filing_id ON DELETE RESTRICT
financial_metrics.filing_id -> filings.filing_id ON DELETE RESTRICT
evidence_records.filing_id -> filings.filing_id ON DELETE RESTRICT
debt_records.filing_id -> filings.filing_id ON DELETE RESTRICT
audit_logs.filing_id -> filings.filing_id ON DELETE SET NULL
```

JSON arrays such as `normalized_fact_ids`, `input_fact_ids`, and `evidence_ids` cannot have ordinary relational FKs in MySQL v1. Their lineage must be verified in application/contract tests later.

---

# Task 1: Repository and Migration Baseline Verification

**Files:**
- Read: `financial-data-engine-sandbox/AGENTS.md`
- Read: `financial-data-engine-sandbox/composer.json`
- Read: `financial-data-engine-sandbox/.env.example`
- Read: `contracts/v1/*.json`
- Inspect: `financial-data-engine-sandbox/database/migrations/`

**Interfaces:**
- Consumes: current repository state and contract v1.
- Produces: verified implementation baseline; no schema changes yet.

- [ ] **Step 1: Enter the Laravel application root**

```bash
cd financial-data-engine-sandbox
```

- [ ] **Step 2: Read agent instructions**

```bash
cat AGENTS.md
```

Follow the current instructions in that file. If repository tooling rewrites `AGENTS.md`, read it again before proceeding.

- [ ] **Step 3: Verify PHP and Composer**

```bash
php -v
composer -V
```

- [ ] **Step 4: Verify Laravel version**

```bash
php artisan --version
```

Expected: Laravel 13.x consistent with `composer.json`.

- [ ] **Step 5: Run migration status and baseline tests**

```bash
php artisan migrate:status
php artisan test
```

Expected: baseline passes before financial schema changes.

---

# Task 2: Create Schema Test Skeleton

**Files:**
- Create: `tests/Feature/Database/FinancialDataSchemaTest.php`

**Interfaces:**
- Produces: schema assertions used by all migration tasks.

- [ ] **Step 1: Create the test directory**

```bash
mkdir -p tests/Feature/Database
```

- [ ] **Step 2: Add an initial failing Pest test**

```php
<?php

use Illuminate\Support\Facades\Schema;

it('creates the financial data engine core tables', function () {
    expect(Schema::hasTable('filings'))->toBeTrue()
        ->and(Schema::hasTable('xbrl_contexts'))->toBeTrue()
        ->and(Schema::hasTable('xbrl_units'))->toBeTrue()
        ->and(Schema::hasTable('xbrl_dimensions'))->toBeTrue()
        ->and(Schema::hasTable('raw_facts'))->toBeTrue()
        ->and(Schema::hasTable('canonical_concepts'))->toBeTrue()
        ->and(Schema::hasTable('concept_mappings'))->toBeTrue()
        ->and(Schema::hasTable('normalized_facts'))->toBeTrue()
        ->and(Schema::hasTable('validation_rules'))->toBeTrue()
        ->and(Schema::hasTable('validation_results'))->toBeTrue()
        ->and(Schema::hasTable('financial_metrics'))->toBeTrue()
        ->and(Schema::hasTable('evidence_records'))->toBeTrue()
        ->and(Schema::hasTable('debt_records'))->toBeTrue()
        ->and(Schema::hasTable('audit_logs'))->toBeTrue();
});
```

- [ ] **Step 3: Verify failure**

```bash
php artisan test tests/Feature/Database/FinancialDataSchemaTest.php
```

- [ ] **Step 4: Commit test skeleton**

```bash
git add tests/Feature/Database/FinancialDataSchemaTest.php
git commit -m "test: define financial data schema expectations"
```

---

# Task 3: Implement `filings`

**Files:**
- Create: `database/migrations/<timestamp>_create_filings_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

**Consumes:** `contracts/v1/filing-metadata.json`.

**Produces:** `filings.filing_id` as stable root identity.

## Required columns

```text
filing_id
issuer_code
report_type
fiscal_year
fiscal_period
period_start
period_end
source_url
source_type
storage_path
source_hash
revision_number
supersedes_filing_id
discovered_at
downloaded_at
created_at
updated_at
```

## Migration shape

```php
Schema::create('filings', function (Blueprint $table) {
    $table->string('filing_id', 128)->primary();
    $table->string('issuer_code', 32);
    $table->string('report_type', 50)->nullable();
    $table->unsignedSmallInteger('fiscal_year')->nullable();
    $table->string('fiscal_period', 20)->nullable();
    $table->date('period_start')->nullable();
    $table->date('period_end');
    $table->text('source_url');
    $table->string('source_type', 50);
    $table->text('storage_path')->nullable();
    $table->string('source_hash', 128);
    $table->unsignedInteger('revision_number');
    $table->string('supersedes_filing_id', 128)->nullable();
    $table->dateTimeTz('discovered_at')->nullable();
    $table->dateTimeTz('downloaded_at')->nullable();
    $table->timestamps();

    $table->index('issuer_code');
    $table->index('period_end');
    $table->index(['issuer_code', 'fiscal_year', 'fiscal_period']);
    $table->index('source_hash');
    $table->index('supersedes_filing_id');

    $table->foreign('supersedes_filing_id')
        ->references('filing_id')
        ->on('filings')
        ->restrictOnDelete();
});
```

Contract vocabulary for `source_type`:

```text
XBRL_INSTANCE
INLINE_XBRL
XLSX
PDF
OTHER
```

- [ ] Write column assertions.
- [ ] Run test and confirm failure.
- [ ] Run `php artisan make:migration create_filings_table`.
- [ ] Implement migration and reversible `down()`.
- [ ] Run `php artisan migrate:fresh`.
- [ ] Run focused schema test.
- [ ] Commit with `feat: add filings schema`.

---

# Task 4: Implement XBRL Context, Unit, and Dimension Tables

**Files:**
- Create: `database/migrations/<timestamp>_create_xbrl_contexts_table.php`
- Create: `database/migrations/<timestamp>_create_xbrl_units_table.php`
- Create: `database/migrations/<timestamp>_create_xbrl_dimensions_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

## `xbrl_contexts`

```php
Schema::create('xbrl_contexts', function (Blueprint $table) {
    $table->string('context_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->string('source_context_id', 255);
    $table->string('entity_identifier', 255);
    $table->string('scope', 50)->nullable();
    $table->string('period_type', 50);
    $table->date('instant_date')->nullable();
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->string('context_status', 50);
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->unique(['filing_id', 'source_context_id']);
    $table->index('filing_id');
});
```

Vocabulary:

```text
scope: CONSOLIDATED | PARENT | UNKNOWN
period_type: INSTANT | DURATION | FOREVER
context_status: RESOLVED | REVIEW_REQUIRED | INVALID
```

## `xbrl_units`

```php
Schema::create('xbrl_units', function (Blueprint $table) {
    $table->string('unit_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->string('source_unit_id', 255);
    $table->string('unit_type', 50);
    $table->string('measure', 255)->nullable();
    $table->string('currency', 32)->nullable();
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->unique(['filing_id', 'source_unit_id']);
    $table->index('filing_id');
});
```

Vocabulary:

```text
CURRENCY | SHARES | RATIO | PURE | CUSTOM | UNKNOWN
```

## `xbrl_dimensions`

```php
Schema::create('xbrl_dimensions', function (Blueprint $table) {
    $table->string('dimension_id', 128)->primary();
    $table->string('context_id', 128);
    $table->string('axis', 255);
    $table->string('member', 255)->nullable();
    $table->text('typed_value')->nullable();
    $table->timestamps();

    $table->foreign('context_id')->references('context_id')->on('xbrl_contexts')->restrictOnDelete();
    $table->index('context_id');
    $table->index('axis');
});
```

- [ ] Add exact column assertions for all three tables.
- [ ] Verify tests fail before migrations.
- [ ] Generate the three migrations.
- [ ] Implement the schemas above.
- [ ] Run `php artisan migrate:fresh` and focused tests.
- [ ] Commit with `feat: add XBRL context unit and dimension schema`.

---

# Task 5: Implement `raw_facts`

**Files:**
- Create: `database/migrations/<timestamp>_create_raw_facts_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('raw_facts', function (Blueprint $table) {
    $table->string('raw_fact_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->string('source_concept', 255);
    $table->string('source_namespace', 255)->nullable();
    $table->longText('raw_value');
    $table->decimal('normalized_numeric_value', 65, 18)->nullable();
    $table->string('context_ref', 128);
    $table->string('unit_ref', 128)->nullable();
    $table->string('decimals', 50)->nullable();
    $table->string('precision', 50)->nullable();
    $table->boolean('is_nil');
    $table->string('fact_status', 50);
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->foreign('context_ref')->references('context_id')->on('xbrl_contexts')->restrictOnDelete();
    $table->foreign('unit_ref')->references('unit_id')->on('xbrl_units')->restrictOnDelete();

    $table->index('filing_id');
    $table->index('source_concept');
    $table->index('context_ref');
    $table->index('unit_ref');
    $table->index('fact_status');
});
```

Vocabulary:

```text
EXTRACTED | UNSUPPORTED | PARSE_ERROR
```

- [ ] Add column assertions.
- [ ] Add insert/read test with filing, context, unit, and raw fact.
- [ ] Verify stable IDs are preserved.
- [ ] Generate and implement migration.
- [ ] Run fresh migration and tests.
- [ ] Commit with `feat: add immutable raw facts schema`.

---

# Task 6: Implement Canonical Concepts and Mapping Rules

**Files:**
- Create: `database/migrations/<timestamp>_create_canonical_concepts_table.php`
- Create: `database/migrations/<timestamp>_create_concept_mappings_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

## `canonical_concepts`

Internal table required by Phase 3; there is no standalone v1 JSON contract.

```php
Schema::create('canonical_concepts', function (Blueprint $table) {
    $table->string('code', 100)->primary();
    $table->string('name', 255);
    $table->string('statement', 50);
    $table->string('period_type', 50);
    $table->string('sign_convention', 50)->nullable();
    $table->json('allowed_scope')->nullable();
    $table->string('required_status', 50)->nullable();
    $table->json('formula_dependency')->nullable();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

Do not seed canonical concepts in the migration.

## `concept_mappings`

```php
Schema::create('concept_mappings', function (Blueprint $table) {
    $table->string('mapping_rule_id', 128)->primary();
    $table->string('source_concept', 255);
    $table->string('entry_point', 255)->nullable();
    $table->string('canonical_concept', 100);
    $table->json('allowed_scope')->nullable();
    $table->string('period_type', 50)->nullable();
    $table->string('sign_convention', 50)->nullable();
    $table->string('status', 50);
    $table->text('rationale')->nullable();
    $table->string('reviewer', 255)->nullable();
    $table->unsignedInteger('rule_version');
    $table->timestamps();

    $table->foreign('canonical_concept')->references('code')->on('canonical_concepts')->restrictOnDelete();
    $table->index('source_concept');
    $table->index('canonical_concept');
    $table->index('status');
    $table->index(['source_concept', 'entry_point', 'rule_version']);
});
```

Vocabulary:

```text
DRAFT | APPROVED | REJECTED | REVIEW_REQUIRED
```

- [ ] Add schema assertions.
- [ ] Add test mapping `Assets` -> `total_assets`.
- [ ] Verify unknown canonical concept violates FK.
- [ ] Generate migrations and implement.
- [ ] Run tests.
- [ ] Commit with `feat: add canonical concepts and mapping schema`.

---

# Task 7: Implement `normalized_facts`

**Files:**
- Create: `database/migrations/<timestamp>_create_normalized_facts_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('normalized_facts', function (Blueprint $table) {
    $table->string('normalized_fact_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->string('raw_fact_id', 128);
    $table->string('issuer_code', 32);
    $table->string('period', 50)->nullable();
    $table->date('period_start')->nullable();
    $table->date('period_end')->nullable();
    $table->string('canonical_concept', 100);
    $table->decimal('value', 65, 18)->nullable();
    $table->string('currency', 32)->nullable();
    $table->string('scope', 50)->nullable();
    $table->string('data_type', 50);
    $table->string('source_concept', 255);
    $table->string('mapping_rule_id', 128);
    $table->unsignedInteger('mapping_rule_version');
    $table->string('normalization_status', 50);
    $table->string('validation_status', 50);
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->foreign('raw_fact_id')->references('raw_fact_id')->on('raw_facts')->restrictOnDelete();
    $table->foreign('canonical_concept')->references('code')->on('canonical_concepts')->restrictOnDelete();
    $table->foreign('mapping_rule_id')->references('mapping_rule_id')->on('concept_mappings')->restrictOnDelete();

    $table->index('filing_id');
    $table->index('raw_fact_id');
    $table->index('canonical_concept');
    $table->index('mapping_rule_id');
    $table->index('normalization_status');
    $table->index('validation_status');
});
```

Vocabulary:

```text
scope: CONSOLIDATED | PARENT | UNKNOWN
data_type: REPORTED | DERIVED
normalization_status: NORMALIZED | UNMAPPED | REVIEW_REQUIRED | FAILED
validation_status: PENDING | VERIFIED | REVIEW_REQUIRED | FAILED
```

Contract caveat: do not weaken `canonical_concept` or `mapping_rule_id` to support `UNMAPPED`; the contract must be revised separately if unmapped normalized rows are required.

- [ ] Add exact column assertions.
- [ ] Add happy-path lineage test: filing -> raw fact -> mapping -> normalized fact.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add normalized facts schema`.

---

# Task 8: Implement Validation Rule Definitions

**Files:**
- Create: `database/migrations/<timestamp>_create_validation_rules_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('validation_rules', function (Blueprint $table) {
    $table->id();
    $table->string('rule_code', 100);
    $table->text('description');
    $table->string('severity', 50);
    $table->json('inputs')->nullable();
    $table->decimal('tolerance', 65, 18)->nullable();
    $table->boolean('enabled')->default(true);
    $table->unsignedInteger('rule_version');
    $table->timestamps();

    $table->unique(['rule_code', 'rule_version']);
    $table->index('enabled');
    $table->index('severity');
});
```

Vocabulary:

```text
ERROR | WARN | INFO
```

- [ ] Add schema assertions.
- [ ] Add unique `(rule_code, rule_version)` test.
- [ ] Verify same rule code is allowed with a different version.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add validation rule definitions`.

---

# Task 9: Implement `validation_results`

**Files:**
- Create: `database/migrations/<timestamp>_create_validation_results_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('validation_results', function (Blueprint $table) {
    $table->string('validation_result_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->json('normalized_fact_ids')->nullable();
    $table->string('rule_code', 100);
    $table->unsignedInteger('rule_version');
    $table->string('result', 50);
    $table->string('severity', 50);
    $table->text('message')->nullable();
    $table->decimal('expected_value', 65, 18)->nullable();
    $table->decimal('actual_value', 65, 18)->nullable();
    $table->decimal('tolerance', 65, 18)->nullable();
    $table->dateTimeTz('checked_at');
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->index('filing_id');
    $table->index(['rule_code', 'rule_version']);
    $table->index('result');
    $table->index('severity');
});
```

Vocabulary:

```text
result: PASS | FAIL | REVIEW_REQUIRED | SKIPPED
severity: ERROR | WARN | INFO
```

Do not create a pivot for `normalized_fact_ids` in v1.

- [ ] Add schema assertions.
- [ ] Add insert/read test for `normalized_fact_ids` JSON.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add validation results schema`.

---

# Task 10: Implement `financial_metrics`

**Files:**
- Create: `database/migrations/<timestamp>_create_financial_metrics_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('financial_metrics', function (Blueprint $table) {
    $table->string('metric_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->string('issuer_code', 32);
    $table->string('period', 50)->nullable();
    $table->string('metric_code', 100);
    $table->decimal('value', 65, 18)->nullable();
    $table->string('unit', 50)->nullable();
    $table->unsignedInteger('formula_version');
    $table->json('input_fact_ids');
    $table->string('calculation_status', 50);
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->index('filing_id');
    $table->index('issuer_code');
    $table->index('metric_code');
    $table->index(['issuer_code', 'period', 'metric_code', 'formula_version']);
});
```

Vocabulary:

```text
CALCULATED | INSUFFICIENT_DATA | REVIEW_REQUIRED | FAILED
```

- [ ] Add schema assertions.
- [ ] Add insert/read test for `input_fact_ids` JSON.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add financial metrics schema`.

---

# Task 11: Implement `evidence_records`

**Files:**
- Create: `database/migrations/<timestamp>_create_evidence_records_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('evidence_records', function (Blueprint $table) {
    $table->string('evidence_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->text('source_document');
    $table->text('source_section')->nullable();
    $table->unsignedInteger('page')->nullable();
    $table->string('source_concept', 255)->nullable();
    $table->string('context_ref', 128)->nullable();
    $table->longText('text_reference')->nullable();
    $table->string('extraction_method', 50);
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->index('filing_id');
    $table->index('extraction_method');
});
```

Vocabulary:

```text
XBRL | IXBRL | XLSX | PDF_MANUAL_REVIEW | OTHER
```

Do not make `context_ref` a strict FK in v1 because PDF/XLSX evidence may have no XBRL context.

- [ ] Add schema assertions.
- [ ] Add XBRL evidence insert/read test.
- [ ] Add PDF evidence test with null `context_ref`.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add evidence records schema`.

---

# Task 12: Implement `debt_records`

**Files:**
- Create: `database/migrations/<timestamp>_create_debt_records_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

```php
Schema::create('debt_records', function (Blueprint $table) {
    $table->string('debt_record_id', 128)->primary();
    $table->string('filing_id', 128);
    $table->string('borrower', 255);
    $table->string('creditor', 255)->nullable();
    $table->string('facility', 255)->nullable();
    $table->decimal('outstanding_amount', 65, 18)->nullable();
    $table->decimal('limit_amount', 65, 18)->nullable();
    $table->string('currency', 32)->nullable();
    $table->decimal('interest_or_profit_rate', 38, 18)->nullable();
    $table->date('maturity_date')->nullable();
    $table->text('collateral')->nullable();
    $table->text('purpose')->nullable();
    $table->string('classification', 50);
    $table->string('classification_status', 50);
    $table->json('evidence_ids');
    $table->timestamps();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
    $table->index('filing_id');
    $table->index('classification');
    $table->index('classification_status');
});
```

Vocabulary:

```text
classification: CONVENTIONAL | ISLAMIC | MIXED | UNDETERMINED
classification_status: SUPPORTED_BY_EVIDENCE | REVIEW_REQUIRED | UNDETERMINED
```

Do not create `creditors`, `creditor_aliases`, or `debt_facilities` in this v1 contract-mirror migration.

- [ ] Add schema assertions.
- [ ] Add insert/read test with one evidence ID.
- [ ] Verify evidence IDs serialize to the contract shape.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add debt records schema`.

---

# Task 13: Implement `audit_logs`

**Files:**
- Create: `database/migrations/<timestamp>_create_audit_logs_table.php`
- Modify: `tests/Feature/Database/FinancialDataSchemaTest.php`

There is no `audit_log` JSON contract in v1; this table is internal and driven by the Phase 3 work plan.

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('actor_id', 128)->nullable();
    $table->string('action', 100);
    $table->string('entity_type', 100);
    $table->string('entity_id', 128);
    $table->json('old_value')->nullable();
    $table->json('new_value')->nullable();
    $table->text('rationale')->nullable();
    $table->string('filing_id', 128)->nullable();
    $table->string('correlation_id', 128)->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->foreign('filing_id')->references('filing_id')->on('filings')->nullOnDelete();
    $table->index('actor_id');
    $table->index('action');
    $table->index('entity_type');
    $table->index('entity_id');
    $table->index('filing_id');
    $table->index('created_at');
});
```

Audit rows are append-oriented. Do not add `updated_at`.

- [ ] Add schema assertions.
- [ ] Add mapping-change audit insert/read test.
- [ ] Verify `old_value` and `new_value` preserve JSON.
- [ ] Generate and implement migration.
- [ ] Run tests.
- [ ] Commit with `feat: add audit log schema`.

---

# Task 14: Add Contract-to-Database Parity Tests

**Files:**
- Create: `tests/Feature/Database/ContractSchemaParityTest.php`

The purpose is regression protection against accidental column renaming/removal.

```php
<?php

use Illuminate\Support\Facades\Schema;

dataset('contract_table_columns', [
    'filing metadata' => [
        'filings',
        [
            'filing_id',
            'issuer_code',
            'report_type',
            'fiscal_year',
            'fiscal_period',
            'period_start',
            'period_end',
            'source_url',
            'source_type',
            'storage_path',
            'source_hash',
            'revision_number',
            'supersedes_filing_id',
            'discovered_at',
            'downloaded_at',
        ],
    ],
    'raw fact' => [
        'raw_facts',
        [
            'raw_fact_id',
            'filing_id',
            'source_concept',
            'source_namespace',
            'raw_value',
            'normalized_numeric_value',
            'context_ref',
            'unit_ref',
            'decimals',
            'precision',
            'is_nil',
            'fact_status',
        ],
    ],
]);

it('keeps contract fields available as database columns', function (
    string $table,
    array $columns,
) {
    expect(Schema::hasColumns($table, $columns))->toBeTrue();
})->with('contract_table_columns');
```

Expand the dataset for:

```text
filings
xbrl_contexts
xbrl_units
xbrl_dimensions
raw_facts
concept_mappings
normalized_facts
validation_results
financial_metrics
evidence_records
debt_records
```

Exclude internal-only tables:

```text
canonical_concepts
validation_rules
audit_logs
```

- [ ] Implement complete parity dataset.
- [ ] Run focused tests.
- [ ] Commit with `test: enforce contract database field parity`.

---

# Task 15: Add Referential Integrity Tests

**Files:**
- Create: `tests/Feature/Database/FinancialDataForeignKeyTest.php`

Required scenarios:

```text
raw fact rejects unknown filing_id
raw fact rejects unknown context_ref
raw fact rejects unknown unit_ref when non-null
normalized fact rejects unknown raw_fact_id
normalized fact rejects unknown canonical_concept
normalized fact rejects unknown mapping_rule_id
validation result rejects unknown filing_id
metric rejects unknown filing_id
evidence rejects unknown filing_id
debt record rejects unknown filing_id
```

Use `Illuminate\Database\QueryException` for failure assertions. Each test must create valid prerequisite rows so only one FK is broken per test.

Example:

```php
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects a raw fact with an unknown filing', function () {
    expect(fn () => DB::table('raw_facts')->insert([
        'raw_fact_id' => 'fact_missing_filing',
        'filing_id' => 'filing_missing',
        'source_concept' => 'Assets',
        'raw_value' => '100',
        'context_ref' => 'ctx_missing',
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
```

- [ ] Implement isolated FK tests.
- [ ] Run `php artisan test tests/Feature/Database/FinancialDataForeignKeyTest.php`.
- [ ] Commit with `test: verify financial data foreign keys`.

---

# Task 16: Add Decimal Precision Tests

**Files:**
- Create: `tests/Feature/Database/FinancialDecimalPrecisionTest.php`

Test fixed-point storage with values that fit `DECIMAL(65,18)`.

Example:

```php
it('preserves normalized financial decimal precision', function () {
    // Create required lineage fixtures first.

    DB::table('normalized_facts')->insert([
        // all required identifiers and states,
        'value' => '42800000000000.123456789012345678',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stored = DB::table('normalized_facts')
        ->where('normalized_fact_id', 'nf_precision')
        ->value('value');

    expect((string) $stored)
        ->toBe('42800000000000.123456789012345678');
});
```

Rules:

- Never cast through PHP float.
- If MySQL returns a formatting variant, normalize string formatting only.
- Never change financial columns to `FLOAT` or `DOUBLE` to make tests easier.

- [ ] Implement precision fixtures and assertions.
- [ ] Run focused test.
- [ ] Commit with `test: protect financial decimal precision`.

---

# Task 17: Verify Rollback and Fresh Migration

- [ ] Run fresh migration:

```bash
php artisan migrate:fresh
```

- [ ] Inspect status:

```bash
php artisan migrate:status
```

- [ ] Roll back:

```bash
php artisan migrate:rollback
```

Expected: no FK ordering errors.

- [ ] Migrate again:

```bash
php artisan migrate
```

- [ ] Run full tests:

```bash
php artisan test
```

- [ ] Run formatter:

```bash
./vendor/bin/pint
```

- [ ] Run tests again after formatting:

```bash
php artisan test
```

- [ ] Commit only if verification required actual file changes.

---

# Task 18: Final Schema Review Against Contract v1

Verify mappings:

```text
filing_metadata      -> filings
xbrl_context         -> xbrl_contexts
xbrl_unit            -> xbrl_units
xbrl_dimension       -> xbrl_dimensions
raw_fact             -> raw_facts
mapping_rule         -> concept_mappings
normalized_fact      -> normalized_facts
validation_result    -> validation_results
fundamental_metric   -> financial_metrics
evidence_record      -> evidence_records
debt_record          -> debt_records
```

Internal persistence-only tables:

```text
canonical_concepts
validation_rules
audit_logs
```

Final review checklist:

- [ ] Every v1 contract property has a corresponding database column in its mapped table.
- [ ] Column names match contract property names.
- [ ] Contract nullability is preserved.
- [ ] Enum/status strings are not renamed.
- [ ] Financial decimals use fixed-point types.
- [ ] Raw lexical values remain text.
- [ ] Stable IDs remain strings.
- [ ] Root lineage starts at `filings.filing_id`.
- [ ] Raw facts reference filing/context/unit.
- [ ] Normalized facts reference filing/raw/mapping/canonical concept.
- [ ] Validation, metric, debt, and evidence records reference their filing.
- [ ] Revision history uses `supersedes_filing_id`.
- [ ] No destructive cascade can silently remove raw lineage.
- [ ] Contract arrays remain JSON in v1.
- [ ] No unsupported `filing_artifacts`, creditor master, facility master, or pivot schema was added.
- [ ] `php artisan migrate:fresh` passes.
- [ ] `php artisan test` passes.
- [ ] Laravel Pint passes.

---

# Expected Final Schema

```text
filings
│
├── xbrl_contexts
│   └── xbrl_dimensions
│
├── xbrl_units
│
├── raw_facts
│
├── normalized_facts
│
├── validation_results
│
├── financial_metrics
│
├── evidence_records
│
├── debt_records
│
└── audit_logs

canonical_concepts
│
└── concept_mappings
     │
     └── normalized_facts

validation_rules
     │
     └── validation_results (logical rule_code + rule_version)
```

Lineage:

```text
filing
  ↓
context + unit + dimension
  ↓
raw_fact
  ↓
mapping_rule + canonical_concept
  ↓
normalized_fact
  ↓
validation_result
  ↓
financial_metric

filing
  ↓
evidence_record
  ↓
debt_record

all important Ops/domain changes
  ↓
audit_logs
```

---

# Out of Scope for This Migration Plan

Do not implement these in this plan:

```text
filing_artifacts table
creditors table
creditor_aliases table
debt_facilities table
metric pivot/input tables
validation result fact pivot
debt evidence pivot
Eloquent models
factories
seeders for DA datasets
Python/Arelle worker
queue jobs
normalization service
validation execution engine
metric calculation engine
HISSA Ops UI
API resources
OpenAPI
production database integration
```

These require a later contract version, DA deliverable, or separate implementation plan.

---

# Definition of Done

The migration implementation is complete only when:

- [ ] Laravel migrations exist for all tables in this plan.
- [ ] Migration order resolves all FK dependencies.
- [ ] `php artisan migrate:fresh` succeeds on MySQL.
- [ ] `php artisan migrate:rollback` completes without FK errors.
- [ ] Contract field names are preserved.
- [ ] Contract nullability is preserved.
- [ ] Contract status/enum strings are preserved.
- [ ] Stable identifiers remain stable string identifiers.
- [ ] Raw facts retain source lexical values.
- [ ] Financial numeric fields use fixed-point decimal storage.
- [ ] Core relational lineage is enforced with foreign keys.
- [ ] JSON array fields preserve contract v1 shape.
- [ ] No raw-lineage table uses destructive cascade delete.
- [ ] Contract-schema parity tests pass.
- [ ] Foreign-key tests pass.
- [ ] Decimal precision tests pass.
- [ ] Full Laravel test suite passes.
- [ ] Laravel Pint passes.
- [ ] No contract file was silently changed as part of migration implementation.

---

# Codex Execution Instruction

Codex must execute this plan task-by-task, not generate all migrations in one uncontrolled pass.

For every task:

```text
read contract
    ↓
write failing schema/behavior test
    ↓
run test and confirm failure
    ↓
create migration
    ↓
run migrate:fresh
    ↓
run focused test
    ↓
review diff
    ↓
commit
```

If implementation discovers a mismatch between a real pilot XBRL requirement and `contracts/v1`:

1. Stop the affected migration task.
2. Do not invent a new field.
3. Do not silently relax nullability.
4. Do not rename a contract field.
5. Record the exact conflict.
6. Propose a contract change separately.
7. Resume migration only after the contract decision is explicit.

The target of this phase is not the most normalized possible schema. The target is a **clear, reproducible, contract-consistent v1 database foundation** that can support the next pipeline phases without hidden semantic translation.
