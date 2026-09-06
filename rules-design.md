# HISSA Financial Data Engine — Backend Technical Design Rules

## 1. Purpose and Authority

This document is the steering document for the entire HISSA Financial Data
Engine backend. It defines the technical boundaries and rules that must be
followed when designing, implementing, reviewing, or refactoring backend code.

The keywords **MUST**, **MUST NOT**, **SHOULD**, and **MAY** are normative:

- **MUST / MUST NOT**: mandatory unless an approved architectural decision
  explicitly replaces the rule.
- **SHOULD / SHOULD NOT**: the default; deviations require a written rationale
  in the pull request or architectural decision record.
- **MAY**: optional and chosen according to the use case.

This document covers the Laravel application, Python XBRL worker, shared data
contracts, persistence, queue processing, APIs, security, observability, and
testing. It does not define financial mappings or validation semantics that
must be supplied and reviewed by the Data Analyst.

### Source-of-truth order

When two sources conflict, use this order:

1. Approved `contracts/v1` files for data semantics and lineage.
2. This `rules-design.md` for backend-wide technical design.
3. Approved feature or phase design documents such as `Design.md` for
   feature-specific decisions.
4. Existing implementation and tests for behavior not covered above.

Any deliberate exception MUST be documented. A feature-specific document MUST
NOT silently override a backend-wide rule.

---

## 2. Current Technology Baseline

The repository manifests and installed dependencies are authoritative. Do not
copy old version assumptions from planning documents.

| Concern | Current baseline | Rule |
|---|---|---|
| PHP | Runtime 8.4; Composer constraint `^8.3` | Use PHP features supported by the Composer constraint unless the constraint is intentionally raised. |
| Backend framework | Laravel 13; currently installed `13.29.x` | Use Laravel 13 APIs and verify version-sensitive behavior against installed packages. |
| ORM and database access | Eloquent + Laravel Database | Prefer framework-native transactions, query builder, casts, relationships, and pagination. |
| Primary application database | MySQL via `.env.example` | MySQL is the application persistence target; SQLite MAY be used for isolated tests only when behavior remains equivalent. |
| Queue API | Laravel Queue | Pipeline jobs MUST implement Laravel queue conventions and run outside HTTP requests. |
| Queue backend | Database is the current local default; Redis is the required pipeline target | Do not claim Redis is active until `QUEUE_CONNECTION=redis` is configured. Phase 6 completion requires Redis-backed pipeline queues. |
| Cache and locks | Database is the current local default; Redis is available | Distributed pipeline locks MUST use a store that supports atomic locks; Redis is the target for multi-worker execution. |
| Worker process | `php artisan queue:work` | Horizon is not installed and MUST NOT be assumed. Process supervision is an operational concern. |
| Frontend bridge | Inertia Laravel 3.3 + Vue 3.5 | Backend web endpoints for HISSA Ops SHOULD return Inertia responses unless they are versioned APIs. |
| Frontend tooling | Bun 1.3, Vite 8, Tailwind CSS 4 | Do not introduce npm/yarn lockfiles or alternate bundlers. |
| PHP testing | Pest 5 + Pest Laravel plugin 5 | New Laravel tests MUST use Pest and existing test conventions. |
| Formatting | Laravel Pint 1.30 | Modified PHP code MUST be formatted with Pint. |
| Parser runtime | Python 3.13 | The parser worker MUST remain compatible with the supported Python 3.13 baseline. |
| XBRL parser | `arelle-release==2.44.4` | Arelle upgrades require separate compatibility review and golden-output regression testing. |
| Shared contract | `contracts/v1` | Cross-runtime data MUST conform to the versioned contract. |

Patch versions may change after dependency updates. `composer.lock`,
`package.json`/lockfile, and Python requirement files remain authoritative.

---

## 3. System Architecture

The backend MUST use a **pragmatic modular monolith**. Laravel remains the
orchestrator and persistence boundary; Python + Arelle is a separate one-shot
process dedicated to XBRL extraction.

```text
HTTP / Inertia / API / Console / Scheduler
                    |
                    v
            Application Services
                    |
                    v
       Domain Rules, Values, and Contracts
                    ^
                    |
    Infrastructure and Persistence Adapters

Asynchronous flow:
Command or Ops action -> Pipeline Orchestrator -> Laravel Queue -> Stage Job
Stage Job -> Application/Domain Service -> MySQL/Storage -> next stage after commit

Parser boundary:
Laravel -> one-shot Python CLI -> deterministic contract JSON -> Laravel validation/persistence
```

This is not a strict academic Clean Architecture implementation. Application
services MAY orchestrate Eloquent models and Laravel transactions. However,
financial/domain rules MUST NOT be placed in controllers, queue job `handle()`
methods, views, or large Eloquent model methods.

### Dependency direction

- Delivery code (`Http`, `Console`, `Jobs`) MAY depend on Application and
  Domain code.
- Application code MAY depend on Domain code, Eloquent models, and explicitly
  required framework services.
- Domain rules and value objects MUST NOT depend on HTTP, Inertia, controllers,
  or UI concerns.
- Infrastructure adapters MUST implement contracts owned by the domain or
  application boundary they serve.
- Lower layers MUST NOT call controllers or queue jobs.
- Cross-domain access SHOULD go through an application service or explicit
  contract when the behavior is more than a simple relationship/query.

---

## 4. Domain Boundaries

Backend behavior SHOULD be organized around business capabilities, not around
generic technical layers alone.

| Domain | Responsibility |
|---|---|
| Filing | Filing identity, issuer/period/revision metadata, source lifecycle |
| XBRL | Artifact extraction, contexts, units, dimensions, and immutable raw facts |
| Mapping | Source-to-canonical concept rules, review state, rationale, and version |
| Financial | Canonical concepts and normalized financial facts |
| Validation | Versioned rules, rule execution, results, and quality aggregation |
| Metrics | Derived metrics with formula and input-fact lineage |
| Debt | Debt records, creditor data, classifications, and evidence |
| Publishing | Eligibility, versioned payloads, snapshots, and delivery contracts |
| Pipeline | Stage transitions, executions, retries, reprocessing, and concurrency |
| Audit | Actor, action, before/after context, rationale, and traceability |

A class MUST have one primary domain responsibility. If a service coordinates
multiple domains, it belongs in the Application layer and SHOULD delegate the
domain decisions to focused collaborators.

---

## 5. Repository and Abstraction Rules

The project MUST NOT implement a repository interface for every Eloquent model.
Wrapping `Model::find()`, `Model::create()`, or a simple relationship query adds
indirection without creating a meaningful boundary.

A repository or gateway abstraction MAY be introduced only when at least one
of these conditions is true:

- the implementation accesses an external provider, storage system, or API;
- more than one real implementation exists or is planned for an approved use
  case;
- a complex reusable query forms a stable business boundary;
- persistence technology is genuinely replaceable at that boundary;
- an integration boundary needs a deterministic test double.

Valid examples include `FilingDiscoverySource`, `XbrlParser`,
`FilingArtifactStorage`, and `FilingPublisher`. An
`EloquentFilingRepository` that only mirrors Eloquent CRUD is prohibited.

Contracts MUST be placed near the boundary that owns their semantics, not in a
global dumping-ground folder.

---

## 6. Repository and Folder Structure

The repository-level structure is:

```text
financial-data-engine/
├── contracts/v1/                    # Versioned cross-runtime data contracts
├── financial-data-engine-sandbox/   # Laravel application
├── python/xbrl-worker/              # Canonical Python/Arelle worker
├── Design.md                        # Phase/feature-specific pipeline design
└── rules-design.md                  # Backend-wide steering rules
```

Prototype or legacy directories are not authoritative integration targets
unless an approved design explicitly promotes them.

The Laravel application SHOULD evolve within this structure:

```text
financial-data-engine-sandbox/
├── app/
│   ├── Application/
│   │   └── <Capability>/             # Use-case orchestration
│   ├── Domain/
│   │   └── FinancialData/
│   │       └── <Domain>/             # Rules, value objects, contracts
│   ├── Infrastructure/
│   │   └── <Integration>/            # External/process/storage adapters
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Jobs/
│   │   └── Pipeline/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   └── Services/                     # Existing focused services; not a catch-all
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/js/
├── routes/
├── storage/app/private/
└── tests/
    ├── Feature/
    └── Unit/
```

Rules for adding folders:

- Follow an existing domain/capability folder before adding a new top-level
  namespace.
- `Services` MUST NOT become a miscellaneous folder. New business logic SHOULD
  be placed in the owning Domain or Application capability.
- `Helpers`, `Utils`, and `Common` folders MUST NOT be added as dumping grounds.
- Shared code must have a clear owner and a narrow, named purpose.
- New infrastructure adapters SHOULD be placed under `Infrastructure` once the
  boundary has a concrete implementation.

---

## 7. Component Responsibilities

### Controllers and HTTP endpoints

Controllers MUST remain thin. They may authenticate/authorize, receive a Form
Request or validated input, call one application operation, and transform the
result into an Inertia, redirect, or API response.

Controllers MUST NOT contain pipeline algorithms, financial calculations,
large Eloquent queries, manual transaction orchestration, or parser calls.

### Form Requests and authorization

- Substantial write-input validation SHOULD use Form Requests.
- Controllers and services MUST consume only validated/whitelisted fields.
- Validation does not replace authorization. State-changing operations MUST use
  policies, gates, or an equivalent explicit authorization boundary.
- Domain invariants MUST also be enforced by database constraints,
  transactions, or domain policies where race conditions are possible.

### Queue jobs

Jobs are asynchronous entry points, not business-logic containers. A job MUST:

1. identify its filing/run/stage;
2. invoke a focused application/domain service;
3. record execution outcome;
4. transition state transactionally;
5. dispatch downstream work only after the transaction commits.

Every pipeline job MUST define or inherit queue name, tries, timeout, backoff,
overlap protection, and failure handling. A failed stage MUST NOT dispatch the
next stage.

### Application services

Application services coordinate use cases, transactions, permissions already
established at the entry boundary, domain collaborators, and output. They MAY
use Eloquent and Laravel facilities explicitly, but MUST NOT duplicate domain
rules across use cases.

### Domain services and value objects

Domain code owns financial/pipeline decisions such as state transitions,
idempotency identities, mapping eligibility, quality aggregation, and publish
eligibility. Domain behavior SHOULD be deterministic and independently
testable.

### Eloquent models

Models own relationships, casts, simple scopes, identity, and persistence
concerns. Large normalization, validation, or orchestration methods MUST NOT be
placed on models. Mass assignment MUST be explicitly controlled.

### Infrastructure adapters

Adapters own external details such as source discovery, HTTP clients, local or
object storage, Python process invocation, and outbound publishing. They MUST
enforce timeouts, validate responses, classify failures, and avoid leaking
vendor-specific structures into domain rules.

---

## 8. Data and Persistence Rules

### Contract-first persistence

`contracts/v1` defines field semantics, nullability, status vocabulary, and
lineage across Laravel and Python. Database tables MAY add IDs, indexes,
timestamps, and operational metadata, but MUST NOT silently change contract
meaning.

Financial decimals crossing runtime or API boundaries MUST use lossless string
representations as defined by the contract. `null` means unavailable; it MUST
NOT be treated as zero or as a workflow status.

### Immutability and revisions

- Source artifacts and successfully persisted raw facts MUST be immutable.
- A revision/restatement MUST create a new filing/version relationship; it MUST
  NOT overwrite prior evidence.
- Derived records MUST retain references to their raw inputs and all applicable
  parser, mapping, formula, rule-set, and contract versions.
- Published outputs MUST be reproducible from stored lineage.

### Transactions and constraints

- Multi-record state changes MUST use a database transaction.
- Queue dispatch that depends on committed data MUST occur after commit.
- Database uniqueness and foreign keys MUST enforce persistent invariants.
- Redis locks prevent concurrent work; they MUST NOT replace database
  idempotency constraints.
- Queries used for pagination or deterministic processing MUST specify stable
  ordering with a unique tie-breaker.
- Large data sets MUST be streamed, chunked, or processed in bounded batches.

### Migrations

- Generate migrations with Artisan.
- Treat migrations already shared or deployed as immutable; use a forward
  migration for changes.
- Add indexes for demonstrated query patterns, not by reflex.
- Large backfills SHOULD use observable, restartable commands/jobs instead of
  long-running schema migrations.
- Destructive or non-reversible migrations require an explicit deployment and
  recovery note.

---

## 9. Pipeline, Queue, and Idempotency

The canonical flow is:

```text
Discovery -> Download -> Parse -> Normalize -> Validate -> Publish
```

The current configured queue names are:

```text
filing-discovery
filing-download
filing-parse
filing-normalize
filing-validate
filing-publish
```

Analytics and enrichment MAY be added as separate queues when their jobs exist;
they MUST NOT be silently folded into validation or publishing.

### Queue rules

- Queue policy MUST be configured in `config/financial-pipeline.php`, not
  scattered as magic numbers.
- `retry_after` MUST exceed the longest possible job timeout with an operational
  safety margin.
- CPU/process-heavy XBRL parsing MUST have isolated concurrency from downloads
  and lightweight stages.
- Workers MUST use `php artisan queue:work`; Horizon-specific APIs or operations
  MUST NOT be introduced unless Horizon is intentionally installed and approved.
- Failed jobs MUST retain enough context to diagnose the filing, pipeline run,
  stage, attempt, and correlation ID.

### Retry versus reprocess

- **Retry** repeats the same job attempt after a transient infrastructure
  failure using the same logical inputs.
- **Reprocess** starts a new domain execution from an explicit stage because
  input, parser, mapping, rule, approval, or contract state changed.
- Deterministic data/domain errors MUST NOT be retried in a loop without a
  relevant state change.
- Reprocessing MUST preserve prior runs and immutable upstream data.

### Idempotency requirements

- Discovery MUST deduplicate stable filing identities and revisions.
- Download MUST deduplicate by approved source identity/hash.
- Parse MUST deduplicate by filing, source hash, parser version, and extraction
  identity.
- Normalize MUST be deterministic for the same raw dataset and mapping version.
- Validate MUST be deterministic for the same normalized dataset and rule-set
  version.
- Publish MUST not create duplicate output for the same publish identity.
- A lock key and database identity MUST include enough version context to avoid
  blocking legitimate new revisions or reprocessing runs.

---

## 10. Laravel–Python/Arelle Boundary

The canonical parser is `python/xbrl-worker`. It is a one-shot CLI process, not
a web service and not a queue consumer.

The Python worker MUST:

- accept one filing artifact and explicit filing/correlation context;
- extract contexts, units, dimensions, and raw facts only;
- emit one deterministic, versioned JSON response on `stdout`;
- emit operational logs on `stderr`;
- return documented exit codes;
- have no database, Redis, HTTP server, or Laravel dependency;
- avoid financial mapping, normalization, and validation decisions.

Laravel MUST:

- resolve the configured Python executable and working directory;
- pass arguments without unsafe shell interpolation;
- enforce a process timeout and capture exit code/stdout/stderr separately;
- validate contract version, filing identity, references, and required fields
  before persistence;
- persist valid output transactionally;
- classify parser failures as transient or terminal;
- record parser and dependency versions for reproducibility.

Parser output is untrusted boundary data even when it comes from the local
worker. Invalid output MUST fail the stage without partial silent persistence.

---

## 11. API and Integration Rules

HISSA Core MUST consume a stable, versioned API or JSON/publish contract. It
MUST NOT read sandbox database tables directly.

- New external API endpoints SHOULD be versioned (`/api/v1/...`).
- Eloquent API Resources SHOULD define response serialization.
- Only data passing the publish eligibility gate may appear as published data.
- Responses MUST include the identifiers and provenance required by the
  approved contract.
- Breaking response changes require a new major contract/API version and a
  migration plan.
- List endpoints MUST use bounded pagination and deterministic ordering.
- Errors MUST use a consistent machine-readable shape and correlation ID.
- Inertia endpoints and external APIs MUST remain separate delivery concerns;
  UI convenience MUST NOT weaken integration contracts.

---

## 12. Security Rules

- Production credentials, databases, and infrastructure MUST NOT be used by the
  sandbox repository.
- Secrets MUST exist only in environment/secret storage. Application code MUST
  read them through configuration, never direct `env()` calls outside config.
- Uploaded/downloaded artifacts MUST be stored outside publicly executable
  paths and validated for size, type, source, and readability.
- Remote source access MUST use an allowlist and MUST defend against SSRF,
  redirects to private networks, unbounded responses, and missing timeouts.
- State-changing Ops actions MUST require authentication, authorization,
  validated input, rate limiting, and audit logging.
- Retry, reprocess, review, and publish actions SHOULD require an idempotency key
  when exposed over HTTP.
- Logs and audit records MUST redact credentials, tokens, authorization headers,
  signed URLs, raw filing payloads, and sensitive absolute paths.
- Dependencies MUST be audited regularly with Composer and the applicable Python
  and JavaScript tooling.

Financial and sharia classifications MUST NOT be guessed. Unknown or ambiguous
states must remain explicit (`UNMAPPED`, `UNKNOWN`, `UNDETERMINED`, or
`REVIEW_REQUIRED`) according to the contract.

---

## 13. Error Handling, Logging, and Audit

Errors MUST be classified so that retry behavior is deliberate:

| Category | Examples | Expected behavior |
|---|---|---|
| Transient infrastructure | timeout, temporary network/DB failure, process resource issue | bounded retry with backoff |
| Terminal input/contract | malformed XBRL, invalid contract payload, unsupported artifact | fail stage and preserve diagnostics |
| Review-required domain state | unmapped concept, ambiguous scope, non-blocking validation issue | persist explicit review state; do not retry blindly |
| Blocking domain state | failed publish eligibility, blocking validation | stop progression and record reason |
| Concurrency conflict | same filing/stage already active | reject, release, or serialize safely without duplicate mutation |

Every pipeline log event SHOULD carry:

```text
filing_id
pipeline_run_id
stage
job/operation
queue
attempt
correlation_id
relevant dependency versions
```

Application logs explain technical execution. Audit logs record meaningful
domain and operator actions. Do not duplicate every debug log into the audit
table.

Audit records MUST identify actor/system, action, timestamp, target entity,
filing/correlation context, rationale, and before/after values when relevant.
Audit history MUST be append-oriented and MUST NOT be silently rewritten.

---

## 14. Testing and Quality Gates

### Laravel tests

- Use Pest for all new tests.
- Prefer feature tests for behavior using Laravel, persistence, queues, HTTP, or
  framework services.
- Use unit tests for pure domain logic and value objects.
- Test observable behavior rather than private implementation details.
- Use factories and focused fixtures; tests MUST NOT depend on production data.
- Use Laravel fakes at external boundaries, but retain integration tests that
  exercise real persistence and orchestration.

Every pipeline stage MUST cover:

- successful execution;
- relevant validation/terminal failure;
- transient failure and retry classification;
- idempotent rerun;
- concurrency/overlap behavior;
- downstream dispatch only after successful persistence;
- no downstream dispatch after failure.

### Python tests

- Use `pytest` for parser unit, contract, CLI, error, and smoke tests.
- Golden/fixture outputs MUST remain deterministic for the same parser version.
- Arelle upgrades require regression tests against approved fixtures.

### Contract and end-to-end tests

- Contract fixtures MUST test required fields, enums, precision, and lineage.
- At least one approved filing MUST pass the complete non-production flow.
- The complete flow MUST be rerun to prove idempotency.
- Negative fixtures MUST demonstrate that malformed, ambiguous, and blocking
  inputs are rejected or routed to explicit review states.

### Required verification

Before merging backend changes:

1. run the narrowest relevant tests during development;
2. run Pint for changed PHP files;
3. run the affected Laravel and/or Python suite;
4. run full tests when the change affects shared contracts, migrations,
   orchestration, or cross-domain behavior;
5. verify no secrets or production connections were introduced;
6. update this document, `Design.md`, contracts, or runbooks when their decisions
   changed.

---

## 15. Coding and Configuration Rules

- Follow PSR-4 and existing Laravel naming conventions.
- Use explicit parameter and return types.
- Prefer constructor injection; avoid `app()` or `resolve()` when normal
  dependency injection is possible.
- Concrete classes not designed for extension SHOULD be `final`.
- Enum case names use StudlyCase; persisted values follow the approved contract.
- Comments explain non-obvious reasons or constraints, not line-by-line behavior.
- Configuration values belong in `config/*.php`; environment variables belong
  in `.env.example` without secrets.
- Magic queue names, timeout values, storage paths, contract versions, and
  provider limits MUST NOT be scattered through implementation code.
- New dependencies require a concrete need, compatibility review, and approval.
- Framework-native capabilities SHOULD be preferred over custom helpers.
- Commands and generated Laravel artifacts SHOULD use `php artisan make:*`
  where an appropriate generator exists.

---

## 16. Architectural Anti-patterns

The following are prohibited unless an approved architectural decision records
a justified exception:

- generic `Controller -> Service -> Repository -> Model` layering for every
  feature;
- one repository interface per Eloquent model;
- business logic embedded in controllers, queue jobs, migrations, or views;
- giant service classes spanning several domains;
- hard-coded mappings, validation semantics, ticker-specific behavior, or
  financial corrections without reviewed rules and provenance;
- mutable raw facts or overwritten source revisions;
- using Redis locks as the only idempotency mechanism;
- treating retry and domain reprocess as the same operation;
- dispatching downstream jobs before upstream data commits;
- silent partial success after parser or persistence failure;
- direct HISSA Core access to internal sandbox tables;
- direct production database or secret access;
- publishing `REVIEW_REQUIRED` or `FAILED` data without an explicit approved
  policy;
- introducing Horizon, a message broker, a microservice, or another framework
  without a demonstrated requirement and approved decision;
- creating abstractions for hypothetical future implementations.

---

## 17. Change and Decision Process

This document describes durable backend rules. Update it when a decision changes
the system globally. Feature-level details belong in a feature design document
or runbook.

An architectural change MUST document:

1. context and problem;
2. decision;
3. alternatives considered;
4. consequences and migration impact;
5. contract, security, operations, and testing impact;
6. approval owner and effective date.

Examples requiring explicit review include:

- changing Laravel/PHP/Python/Arelle major versions;
- changing queue or persistence technology;
- changing contract semantics or lineage;
- adding a new service/process boundary;
- allowing a new publish destination;
- weakening immutability, idempotency, authorization, or audit guarantees;
- adopting Horizon or changing worker topology.

When implementation and this document disagree, do not silently normalize the
documentation to the code. Determine whether the implementation is a defect or
the architecture has intentionally changed, then update the correct source with
review.

---

## 18. Current Known Gaps

These gaps are recorded to keep this document accurate; they are not permission
to bypass the target rules:

- `.env.example` currently sets `QUEUE_CONNECTION=database` and
  `CACHE_STORE=database`. Redis-backed queue/cache execution remains a Phase 6
  configuration and operational completion item.
- Some pipeline jobs currently provide structural queue entry points while
  their stage-specific business services are still under implementation.
- The repository README is still close to the Laravel starter documentation and
  does not yet replace a project runbook.
- `Design.md` refers to an older Laravel 12 assumption. Installed Laravel 13 and
  this document govern current implementation unless a reviewed migration says
  otherwise.

Known gaps SHOULD be removed from this section when their implementation and
verification are complete.

---

## 19. Pull Request Design Checklist

Before approving backend work, confirm:

- [ ] The change belongs to a clear domain/capability.
- [ ] Delivery code is thin and business logic is in the correct layer.
- [ ] No unnecessary repository or speculative abstraction was added.
- [ ] Contract semantics, precision, status, and lineage remain correct.
- [ ] Raw source/history remains immutable.
- [ ] Transactions, after-commit dispatch, idempotency, and concurrency are
      handled where relevant.
- [ ] Retryable and terminal failures are distinguished.
- [ ] Authentication, authorization, validation, rate limits, and redaction are
      addressed where relevant.
- [ ] Logs and audit events include filing/run/correlation context.
- [ ] Relevant Pest, pytest, contract, integration, and idempotency tests pass.
- [ ] Version-sensitive code matches installed dependencies.
- [ ] Documentation and runbooks reflect any changed decision.

