# Publish, Reprocess, and Operations Design

## Context

Tasks 19–25 complete the Phase 5 pipeline after Normalize and Validate. The
existing application already persists immutable source, raw, normalized, and
validation lineage, but `PublishFilingJob` is only a queue shell and there is
no operator-facing reprocess flow, status command, publish snapshot contract,
or complete failed-job/audit handling.

This design follows `requirement.md`, `Design.md`, and `rules-design.md`.
Published data remains a versioned representation owned by the sandbox; HISSA
Core never reads sandbox tables directly.

## Goals and non-goals

Goals:

- Publish only `VERIFIED` filings automatically.
- Persist an immutable, versioned publish snapshot with complete lineage.
- Make publish and reprocess operations deterministic and idempotent.
- Support explicit reprocess starting stages and operator CLI entry points.
- Provide structured operational logs, append-only audit events, and complete
  retry-exhaustion metadata.
- Prove the complete approved-sample flow and its negative/versioned paths.

Non-goals:

- Direct HISSA Core database or production API integration.
- A full Ops UI, authentication system, or new financial calculations.
- Fuzzy mapping, automatic review approval, or destructive cleanup of prior
  artifacts and derived history.

## Architecture

The implementation uses four focused boundaries:

1. Domain publishing owns eligibility, payload construction, and contract
   validation.
2. Application pipeline services coordinate transactions, run metadata, and
   dispatch; queue jobs remain thin entry points.
3. Eloquent persistence stores immutable published snapshots and extended
   pipeline-run metadata.
4. Console commands validate operator input and call application services;
   they never run business stages synchronously.

The existing `PipelineOverlapMiddleware` remains the stage-level concurrency
guard. Reprocess creation also locks the filing row and rejects an active run
for the same filing and requested stage before dispatching work after commit.

## Publish contract

### Eligibility

`PublishEligibilityPolicy::check(Filing $filing): PublishEligibility` returns
an explicit decision and reason. `VERIFIED` is automatically eligible;
`FAILED` is blocked; `REVIEW_REQUIRED` is blocked unless a future explicit
approval policy is supplied. Task 19 does not add an approval bypass.

The job re-checks this policy immediately before building output. A caller or
queued payload must never be trusted as the eligibility source.

### Payload shape

`FilingPublishPayloadBuilder` creates a JSON-serializable array with only
versioned, validated data:

```json
{
  "contract": "hissa.financial-data.publish",
  "contract_version": "1.0.0",
  "filing": {
    "filing_id": "FIL-...",
    "revision_number": 1,
    "issuer_code": "...",
    "report_type": "...",
    "fiscal_year": 2025,
    "fiscal_period": "FY",
    "period_end": "2025-12-31"
  },
  "quality": {
    "status": "VERIFIED",
    "validation_rule_set_version": "rules-..."
  },
  "normalized_facts": [],
  "lineage": {
    "raw_fact_ids": [],
    "normalized_fact_ids": [],
    "validation_result_ids": [],
    "mapping_versions": [],
    "normalization_version": "...",
    "normalized_dataset_version": "..."
  }
}
```

Decimal values are serialized as lossless strings. The builder includes the
current normalized dataset and only validation results belonging to the
validated dataset/rule-set version. It does not mutate any upstream row.

`PublishContractValidator::validate(array $payload): void` rejects missing
contract identity/version, filing identity or revision mismatch, non-verified
quality, empty required lineage, invalid status/enums, malformed numeric
representations, and normalized facts without their raw lineage.

### Snapshot persistence

A forward migration creates `published_snapshots` with:

- `snapshot_id` string primary key;
- `filing_id`, revision number, and restrictive filing foreign key;
- `publish_contract_version`, normalized dataset version, and validation rule
  set version;
- unique `publish_idempotency_key`;
- immutable JSON `payload` and `lineage` columns;
- `published_at` and timestamps;
- indexes for filing/revision and contract version lookup.

The snapshot ID is deterministic from filing, revision, validation run, and
publish contract version. Repeating the same publish identity returns the
existing snapshot without creating another row. A new revision gets a new
snapshot and never overwrites the previous revision.

## Publish job

`PublishFilingJob` uses the configured publish queue, tries, timeout, backoff,
and `PipelineOverlapMiddleware`. It records a `PUBLISHING` job run using
`PipelineIdempotencyKey::publish`, then performs one transaction:

1. lock and reload the filing;
2. re-check eligibility and locate the latest compatible validation run;
3. build and validate the payload;
4. create or reuse the immutable snapshot;
5. mark the filing `PUBLISHED` and the pipeline run `SUCCEEDED`;
6. append `filing.published` audit data with snapshot ID and versions.

Publish stage failure marks the filing/run failed and does not expose a
snapshot. Transient infrastructure errors remain throwable for queue retry;
terminal eligibility and contract errors are recorded without downstream
dispatch.

## Reprocess service

The application service exposes:

```php
public function start(
    string $filingId,
    ReprocessStage $stage,
    string $reason,
    string $actorId = 'system',
): PipelineRun
```

Input is trimmed and validated. User-triggered calls require a non-empty
reason. The service locks the filing, checks required data, rejects an active
run for the same filing/stage, creates a new `PipelineRun`, records
`started_from_stage`, actor, reason, and dependency versions, sets the
corresponding processing stage, and dispatches the first job after commit.

The reprocess matrix is:

| Start | Required prerequisite | First processing stage | Jobs dispatched by the pipeline |
|---|---|---|---|
| `DOWNLOAD` | source locator | `DOWNLOADING` | Download → Parse → Normalize → Validate → Publish eligibility |
| `PARSE` | valid immutable artifact | `PARSING` | Parse → Normalize → Validate → Publish eligibility |
| `NORMALIZE` | raw facts | `NORMALIZING` | Normalize → Validate → Publish eligibility |
| `VALIDATE` | normalized facts | `VALIDATING` | Validate → Publish eligibility |
| `PUBLISH` | eligible validation state | `PUBLISHING` | Publish |

Previous pipeline runs, snapshots, raw facts, normalized facts, and validation
results remain intact. Reprocess audit events record actor, reason, stage,
run/correlation IDs, and pinned dependency versions.

## CLI operations

The console entry points are thin adapters:

- `financial-data:discover {sourceAdapter=...} {discoveryWindow=...}` creates
  and dispatches `DiscoverFilingsJob`; it does not execute discovery inline.
- `financial-data:reprocess {filing} {stage} --reason=... --actor=...` parses
  the enum, requires reason, calls `ReprocessService`, and reports the new run
  and correlation ID.
- `financial-data:status {filing}` is read-only and reports filing stage,
  quality, latest pipeline run, latest job status/error, and publish snapshot
  identity when present.

Invalid filing, stage, missing reason, or missing prerequisite returns a
non-zero command status with a concise operator-safe error. Commands do not
include secrets or full source payloads in output.

## Logging, audit, and failure handling

`PipelineExecutionLogger` applies structured context to pipeline log records:
`filing_id`, `pipeline_run_id`, `stage`, `job`, `queue`, `attempt`,
`correlation_id`, and relevant dependency versions. Existing error sanitizing
is reused and extended to redact tokens, credentials, authorization headers,
signed URLs, and sensitive absolute paths.

Audit records remain append-only and cover publish, reprocess requested,
reprocess started, review-required, and failure/exhaustion. Existing
discovery/download/parse/normalize/validate events are preserved. System
actions use `system`; operator actions use the supplied actor ID.

Every pipeline job has a `failed(Throwable $exception)` hook that delegates to
one failure handler. The handler records the current job attempt, marks the
stage and active run failed, appends `filing.failed`, and never dispatches a
downstream job. Queue retry commands are documented separately from domain
reprocess: retry repeats the same logical job, while reprocess starts a new
versioned run after a relevant state change.

## Testing strategy

Implementation follows red-green-refactor for each behavior:

- unit tests for eligibility, payload/contract validation, reprocess stage
  validation, and log sanitization;
- feature tests for publish persistence/idempotency, revision snapshots,
  reprocess prerequisites/matrix/concurrency, CLI validation/status, audit and
  failure metadata;
- an approved-sample integration test that runs Discovery → Download → Parse →
  Normalize → Validate → Publish with real test persistence and external
  boundaries faked only where required;
- rerun, REVIEW_REQUIRED, FAILED, mapping-version, and rule-version scenarios
  from Task 25;
- Pint and the full Laravel suite before completion.

The E2E test asserts no duplicate filing, artifact, raw fact, normalized fact,
validation result, or published snapshot for a same-version rerun, and asserts
that old history remains after versioned reprocess.

## Decision consequences

The snapshot table adds durable storage but makes publish reproducible without
direct database access. Reprocess metadata becomes queryable without parsing
audit JSON. Publish and reprocess are intentionally explicit operations, so a
`VERIFIED` validation state can remain unpublished until the queued publish
job succeeds. No production secrets, external HISSA connection, or new
dependency is introduced.
