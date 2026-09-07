# Publish, Reprocess, and Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Tasks 19–25: versioned publish snapshots, publish eligibility and contract validation, explicit reprocess operations, CLI operational entry points, structured audit/failure handling, and an approved-sample end-to-end pilot.

**Architecture:** Keep publish decisions and contract rules in `app/Domain/FinancialData/Publishing`, coordinate persistence and dispatch in focused application/services classes, and keep queued jobs and console commands thin. Store immutable publish snapshots and reprocess metadata in forward-only migrations; preserve all raw, derived, validation, execution, and revision history.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Eloquent, Laravel Queue, configured cache lock middleware, local/private storage, and the existing Python/Arelle parser boundary.

**Spec:** `docs/superpowers/specs/2026-09-07-publish-reprocess-operations-design.md`

## Global Constraints

- `VERIFIED` is eligible for automatic publish; `REVIEW_REQUIRED` and `FAILED` are blocked without an explicit approval policy.
- Raw source, `RawFact`, `NormalizedFact`, `ValidationResult`, previous `PipelineRun`, and previous published snapshots are immutable history.
- Publish output uses lossless decimal strings, includes filing/revision identity and complete lineage, and never gives HISSA Core direct sandbox database access.
- Reprocess starts from an explicit `ReprocessStage`, requires a reason for operator-triggered requests, creates a new run, and dispatches only after committed state.
- Jobs use the configured queue/tries/timeout/backoff policy, overlap middleware, execution recorder, and `failed(Throwable $exception)` handling.
- No production database, network endpoint, credential, token, or new dependency may be introduced.
- Every production behavior change is test-first: write the failing test, run it, implement the smallest fix, rerun the narrow test, then run affected/full suites.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.

## File Map

Create:

- `app/Domain/FinancialData/Publishing/PublishEligibility.php` — immutable eligibility decision and reason.
- `app/Domain/FinancialData/Publishing/PublishEligibilityPolicy.php` — quality-to-eligibility policy.
- `app/Domain/FinancialData/Publishing/FilingPublishPayloadBuilder.php` — deterministic publish contract payload.
- `app/Domain/FinancialData/Publishing/PublishContractValidator.php` — payload contract guard.
- `app/Domain/FinancialData/Publishing/Exceptions/InvalidPublishContractException.php` — terminal contract error.
- `app/Domain/FinancialData/Publishing/Exceptions/PublishNotEligibleException.php` — blocked publish error.
- `app/Models/PublishedSnapshot.php` — immutable snapshot model and casts.
- `app/Services/Pipeline/PublishedSnapshotPersistence.php` — idempotent snapshot persistence.
- `app/Services/Pipeline/ReprocessService.php` — reprocess prerequisite, run, audit, and dispatch coordinator.
- `app/Services/Pipeline/PipelineFailureHandler.php` — common queue exhaustion failure behavior.
- `app/Services/Pipeline/PipelineExecutionLogger.php` — structured pipeline log context.
- `app/Console/Commands/DiscoverFilingsCommand.php` — asynchronous discovery command.
- `app/Console/Commands/ReprocessFilingCommand.php` — validated reprocess command.
- `app/Console/Commands/PipelineStatusCommand.php` — read-only filing status command.
- `database/migrations/2026_09_07_110000_create_published_snapshots_table.php` — immutable publish output schema.
- `database/migrations/2026_09_07_110100_extend_pipeline_runs_for_operations.php` — run reason/actor/stage/version metadata.
- `docs/pipeline-operations.md` — queue retry versus domain reprocess runbook required by Task 24.
- `tests/Unit/Domain/FinancialData/Publishing/PublishEligibilityPolicyTest.php`.
- `tests/Unit/Domain/FinancialData/Publishing/PublishContractValidatorTest.php`.
- `tests/Unit/Domain/FinancialData/Publishing/FilingPublishPayloadBuilderTest.php`.
- `tests/Unit/Services/Pipeline/PipelineExecutionLoggerTest.php`.
- `tests/Feature/Pipeline/PublishFilingJobTest.php`.
- `tests/Feature/Pipeline/ReprocessFilingTest.php`.
- `tests/Feature/Console/PipelineCommandsTest.php`.
- `tests/Feature/Pipeline/PipelineFailureHandlingTest.php`.
- `tests/Feature/Pipeline/ApprovedSamplePipelineTest.php`.

Modify:

- `app/Jobs/Pipeline/PublishFilingJob.php` — complete publish execution.
- `app/Jobs/Pipeline/PipelineJob.php` — shared failure/logging hooks only if the concrete stage contract is preserved.
- `app/Jobs/Pipeline/DiscoverFilingsJob.php`, `DownloadFilingJob.php`, `ParseXbrlJob.php`, `NormalizeFactsJob.php`, `ValidateFilingJob.php` — delegate queue exhaustion to the common failure handler and emit structured context.
- `app/Models/PipelineRun.php` — fillable fields/casts/relations for operation metadata.
- `app/Models/Filing.php` — published snapshot relation and corrected run relation name if needed.
- `app/Application/Pipeline/PipelineOrchestrator.php` — use publish eligibility policy and preserve after-commit transitions.
- `app/Domain/FinancialData/Pipeline/PipelineIdempotencyKey.php` — include the final publish identity inputs if the existing key needs the validation run/snapshot identity.
- `app/Providers/AppServiceProvider.php` — bind publishing and operations services.
- `config/financial-pipeline.php` — publish contract version and any configured operational limits.
- `routes/console.php` — register the three commands.
- `task.md` — mark each completed Task 19–25 subtask after verification.

---

### Task 1: Publish domain policy and contract primitives (Task 19.1–19.3)

**Files:**

- Create the four publishing domain classes and two exceptions listed in the file map.
- Test in `tests/Unit/Domain/FinancialData/Publishing/`.

**Interfaces:**

- `PublishEligibilityPolicy::check(Filing $filing): PublishEligibility`.
- `PublishEligibility::allowed(): bool`, `reason(): string`, and `status(): QualityStatus`.
- `FilingPublishPayloadBuilder::build(Filing $filing): array`.
- `PublishContractValidator::validate(array $payload): void`.

- [ ] Write a unit test proving `VERIFIED` returns an allowed decision, while `FAILED` and `REVIEW_REQUIRED` return blocked decisions with non-empty reasons.
- [ ] Run `php artisan test tests/Unit/Domain/FinancialData/Publishing/PublishEligibilityPolicyTest.php --compact` and verify it fails because the classes do not exist.
- [ ] Implement the immutable decision object and policy using `QualityStatus`; do not add an approval bypass.
- [ ] Write validator tests for missing contract identity/version, invalid quality, missing filing identity, empty lineage, malformed decimal values, and valid payload acceptance.
- [ ] Run the validator test and confirm the new failure cases are red before implementation.
- [ ] Implement `PublishContractValidator` with explicit array-shape checks and `InvalidPublishContractException`; keep decimal values as strings.
- [ ] Build the payload test fixture from real `Filing`, `NormalizedFact`, and `ValidationResult` models and assert filing/revision, quality, normalized facts, validation IDs, mapping versions, and raw IDs are present.
- [ ] Implement `FilingPublishPayloadBuilder` with deterministic ordering by normalized fact/result identity and no mutation of input models.
- [ ] Run the three unit files and confirm all pass.

### Task 2: Snapshot schema and idempotent persistence (Task 19.4)

**Files:**

- Create `database/migrations/2026_09_07_110000_create_published_snapshots_table.php`.
- Create `app/Models/PublishedSnapshot.php` and `app/Services/Pipeline/PublishedSnapshotPersistence.php`.
- Modify `Filing.php` with `publishedSnapshots(): HasMany`.
- Test through `tests/Feature/Pipeline/PublishFilingJobTest.php` persistence cases.

**Interfaces:**

- `PublishedSnapshotPersistence::persist(Filing $filing, array $payload, string $idempotencyKey, int $pipelineRunId): PublishedSnapshot`.
- Snapshot primary key is deterministic from filing ID, revision, validation run identity, and contract version.

- [ ] Write a migration/schema test asserting required columns, filing foreign key, unique publish idempotency key, JSON casts, and revision/version indexes.
- [ ] Run the schema test and observe failure because the table/model does not exist.
- [ ] Add the forward migration without editing older migrations; include restrictive filing deletion behavior and immutable payload/lineage JSON columns.
- [ ] Implement the model with explicit fillable attributes, JSON/datetime casts, and no update behavior exposed by the service.
- [ ] Write a feature test persisting the same payload/idempotency key twice and asserting one snapshot, stable snapshot ID, and unchanged payload.
- [ ] Implement persistence with a transaction-safe `firstOrCreate`/unique-key path and deterministic snapshot identity.
- [ ] Run the migration and persistence tests, then run the existing database schema tests.

### Task 3: Complete `PublishFilingJob` (Task 20 and remaining Task 19 tests)

**Files:**

- Modify `app/Jobs/Pipeline/PublishFilingJob.php`, `PipelineOrchestrator.php`, `config/financial-pipeline.php`, and `AppServiceProvider.php`.
- Add publish cases to `tests/Feature/Pipeline/PublishFilingJobTest.php`.

**Interfaces:**

- Job constructor remains `__construct(string $filingId)` and uses `filing-publish`.
- Job `handle` consumes `PublishEligibilityPolicy`, payload builder, validator, snapshot persistence, `JobExecutionRecorder`, and `PipelineOrchestrator` through constructor/handler injection.

- [ ] Write a failing happy-path test with a verified filing and normalized/validated lineage; assert one snapshot, `PUBLISHED`, successful job run, audit action `filing.published`, and no direct external DB call.
- [ ] Run the focused test and verify it fails because the job has no `handle` implementation.
- [ ] Add configured tries, timeout, backoff, and `PipelineOverlapMiddleware` assertions to the test before implementation.
- [ ] Implement job idempotency using `PipelineIdempotencyKey::publish`, latest compatible validation identity, and one DB transaction that persists output before marking published.
- [ ] Write failing tests for `FAILED`, `REVIEW_REQUIRED`, invalid payload, same-version rerun, and a new filing revision.
- [ ] Implement terminal eligibility/contract failure through `PipelineFailureHandler` without snapshot creation or downstream dispatch; reuse an existing snapshot for the same identity and create a distinct snapshot for a new revision.
- [ ] Assert dispatch-after-commit and audit payload includes snapshot ID, contract version, revision, validation version, and correlation ID.
- [ ] Run all publish tests and the existing orchestrator/validation tests.

### Task 4: Reprocess metadata and service (Task 21)

**Files:**

- Create `database/migrations/2026_09_07_110100_extend_pipeline_runs_for_operations.php`.
- Modify `app/Models/PipelineRun.php`, `app/Services/Pipeline/ReprocessService.php`, and `app/Providers/AppServiceProvider.php`.
- Add `tests/Feature/Pipeline/ReprocessFilingTest.php`.

**Interfaces:**

- `ReprocessService::start(string $filingId, ReprocessStage $stage, string $reason, string $actorId = 'system'): PipelineRun`.
- `PipelineRun` stores `started_from_stage`, `initiated_by`, `reason`, and `dependency_versions` JSON in addition to existing fields.

- [ ] Write failing tests for each matrix row: `DOWNLOAD`, `PARSE`, `NORMALIZE`, `VALIDATE`, and `PUBLISH`; assert the filing processing stage and dispatched first job/queue.
- [ ] Run the focused test and verify failure from missing service/migration fields.
- [ ] Add the forward migration and model casts/fillable fields; pin parser, parser config, normalization, mapping, normalized dataset, validation rule-set, and publish contract versions available at request time.
- [ ] Write prerequisite tests for missing source locator, missing valid artifact, missing raw facts, missing normalized facts, and non-verified publish state.
- [ ] Implement prerequisite checks with explicit exceptions/messages before creating a run or dispatching a job.
- [ ] Write tests for empty reason, actor/reason audit data, previous run preservation, and same filing/stage overlap rejection.
- [ ] Implement row locking, active-run check, new `PipelineRun`, `filing.reprocess_requested` and `filing.reprocess_started` audit records, and after-commit first-job dispatch.
- [ ] Assert reprocess from Normalize leaves raw facts unchanged and reprocess from Validate leaves normalized facts unchanged while allowing new validation history.
- [ ] Run all reprocess tests and the pipeline model tests.

### Task 5: Operational CLI commands (Task 22)

**Files:**

- Create the three command classes listed in the file map.
- Modify `routes/console.php` only for registration if class-based commands are not auto-discovered.
- Add `tests/Feature/Console/PipelineCommandsTest.php`.

**Interfaces:**

- `financial-data:discover {sourceAdapter} {discoveryWindow} {--page-size=10}` dispatches `DiscoverFilingsJob` asynchronously.
- `financial-data:reprocess {filing} {stage} {--reason=} {--actor=system}` calls `ReprocessService`.
- `financial-data:status {filing}` prints stage, quality, latest run/job/error, and snapshot ID.

- [ ] Write command tests for successful discovery dispatch, unknown filing, invalid stage, missing reason, successful reprocess, and read-only status output.
- [ ] Run the command test file and confirm failures from missing command signatures.
- [ ] Implement discovery command argument validation and queue dispatch without calling the source adapter inline.
- [ ] Implement reprocess command enum parsing, reason requirement, actor selection, service invocation, success output, and non-zero failures.
- [ ] Implement status command with deterministic latest-run/latest-job ordering and no writes.
- [ ] Run `php artisan list --format=txt` and the command test file to verify command registration and behavior.

### Task 6: Structured logging, audit, and secret redaction (Task 23)

**Files:**

- Create `app/Services/Pipeline/PipelineExecutionLogger.php` and its unit test.
- Modify `JobExecutionRecorder.php`, relevant jobs, `PipelineOrchestrator.php`, `ReprocessService.php`, and `PublishFilingJob.php`.
- Add logging/audit assertions to `tests/Feature/Pipeline/PipelineFailureHandlingTest.php` and publish/reprocess tests.

**Interfaces:**

- `PipelineExecutionLogger::context(Filing|string $filing, ?PipelineRun $run, PipelineStage|string $stage, string $job, string $queue, int $attempt, ?string $correlationId, array $versions = []): array`.
- `PipelineExecutionLogger::withContext(...): void` applies only sanitized structured context to Laravel logs.

- [ ] Write a unit test proving context contains filing/run/stage/job/queue/attempt/correlation and dependency versions.
- [ ] Write a redaction test proving bearer tokens, `api_key`, passwords, authorization headers, signed URLs, and absolute artifact paths are not emitted in raw form.
- [ ] Implement the logger by reusing `JobExecutionRecorder` sanitization rules and adding URL/path redaction.
- [ ] Add domain audit tests for system actor, operator actor/reason, publish snapshot/version data, reprocess stage/reason, review-required, and failure metadata.
- [ ] Implement missing audit events without duplicating every technical debug log into `audit_logs`.
- [ ] Run focused logging/audit tests and existing discovery logging tests.

### Task 7: Queue exhaustion and operations runbook (Task 24)

**Files:**

- Create `app/Services/Pipeline/PipelineFailureHandler.php` and `tests/Feature/Pipeline/PipelineFailureHandlingTest.php`.
- Modify every pipeline job: `DiscoverFilingsJob`, `DownloadFilingJob`, `ParseXbrlJob`, `NormalizeFactsJob`, `ValidateFilingJob`, and `PublishFilingJob`.
- Create `docs/pipeline-operations.md`.

**Interfaces:**

- `PipelineFailureHandler::handle(string $filingId, PipelineStage $stage, Throwable $exception, ?PipelineJobRun $jobRun = null): void` records failed execution/run/stage and audit without dispatching downstream work.
- Each job retains its existing `failed(Throwable $exception): void` public hook and delegates to this handler.

- [ ] Write exhaustion tests invoking each job’s `failed()` hook with a real filing/run/job-run fixture; assert stage `FAILED`, run/job error metadata, `filing.failed` audit, and no downstream job.
- [ ] Run the tests and verify failure because the common handler/hooks are incomplete.
- [ ] Implement the handler transactionally with sanitized exception metadata and active job-run lookup.
- [ ] Update each job hook while preserving terminal-error handling and not deleting source/raw/derived history.
- [ ] Document exact commands `php artisan queue:failed`, `php artisan queue:retry <id>`, and `php artisan queue:retry all`; explain that queue retry keeps the same logical inputs while domain reprocess creates a new run with a reason.
- [ ] Run the failure tests and the full existing pipeline job suite.

### Task 8: Approved-sample end-to-end pilot (Task 25)

**Files:**

- Create `tests/Feature/Pipeline/ApprovedSamplePipelineTest.php`.
- Reuse existing approved discovery fixture, HTTP/storage fakes, parser contract fixture, mappings, and validation rule helpers; add only focused fixture utilities under `tests/Support` if repeated setup is unavoidable.

**Interfaces:**

- Exercise jobs in order: `DiscoverFilingsJob`, `DownloadFilingJob`, `ParseXbrlJob`, `NormalizeFactsJob`, `ValidateFilingJob`, and `PublishFilingJob`.
- Assert persisted domain state, queues, audit events, and immutable histories rather than private methods.

- [ ] Write the happy-path test first using an approved sample and deterministic parser output; assert stage order, `VERIFIED`, one published snapshot, complete lineage, and no production/network dependency.
- [ ] Run the test and verify it fails because publishing is not implemented.
- [ ] Add a full same-source/version rerun and assert filing/artifact/raw/normalized/validation/snapshot counts and identities remain stable.
- [ ] Add a REVIEW_REQUIRED scenario with unmapped or warning validation output and assert no `PublishFilingJob` execution/snapshot.
- [ ] Add a blocking validation scenario and assert `FAILED`, no publish snapshot, and no publish downstream dispatch.
- [ ] Add mapping-version reprocess from Normalize and assert raw facts/artifact unchanged and new normalized/validation versions coexist.
- [ ] Add validation-rule-version reprocess from Validate and assert old `ValidationResult` rows remain alongside the new version.
- [ ] Run the complete E2E test file, then run `php artisan test --compact`.

### Task 9: Documentation, task checklist, and final verification (Task 19–25 completion)

**Files:**

- Modify `task.md` to mark only verified Task 19–25 items complete.
- Do not alter `requirement.md`, `Design.md`, or `rules-design.md` unless implementation changes a durable architectural decision.

- [ ] Run `vendor/bin/pint --dirty --format agent`.
- [ ] Run focused unit tests for publishing, reprocess, commands, logging, and failure handling.
- [ ] Run focused feature tests for publish, reprocess, commands, failures, and approved sample.
- [ ] Run `php artisan migrate:fresh --env=testing` through the test suite/schema tests to verify forward migrations from zero.
- [ ] Run `php artisan test --compact` and confirm no test failure.
- [ ] Run `git diff --check` and inspect `git status --short` for unintended files/secrets.
- [ ] Mark Task 19–25 checkboxes only after all evidence above is green.

## Verification Commands

```powershell
php artisan test tests/Unit/Domain/FinancialData/Publishing --compact
php artisan test tests/Feature/Pipeline/PublishFilingJobTest.php --compact
php artisan test tests/Feature/Pipeline/ReprocessFilingTest.php --compact
php artisan test tests/Feature/Console/PipelineCommandsTest.php --compact
php artisan test tests/Feature/Pipeline/PipelineFailureHandlingTest.php --compact
php artisan test tests/Feature/Pipeline/ApprovedSamplePipelineTest.php --compact
vendor/bin/pint --dirty --format agent
php artisan test --compact
git diff --check
```

