# Financial pipeline operations runbook

## Scope and prerequisites

Phase 6 runs the financial pipeline on Redis-backed Laravel queues without
Horizon. Run commands from `financial-data-engine-sandbox/` with a sandbox
`.env` loaded.

Before starting workers, verify:

- Redis is reachable at the sandbox `REDIS_HOST`, `REDIS_PORT`, and logical
  database configured by the environment.
- MySQL/sandbox database is migrated and writable.
- private storage is writable and the configured parser executable and
  `python/xbrl-worker` directory are available.
- `.env` contains no production database, Redis, password, token, or host.
- `QUEUE_CONNECTION=redis` and `REDIS_CLIENT=predis` are configured for the
  sandbox.

Inspect resolved queue configuration before deployment:

```bash
php artisan config:clear
php artisan config:show queue
php artisan config:show financial-pipeline
php artisan queue:work --help
```

## Canonical topology and worker profiles

Active flow:

```text
discovery -> downloads -> xbrl -> normalize -> validate -> publish
```

| Workload | Connection | Queue | Baseline processes | Timeout | Tries | Retry after |
|---|---|---:|---:|---:|---:|---:|
| Discovery | `redis-discovery` | `discovery` | 1 | 120s | 3 | 180s |
| Downloads | `redis-downloads` | `downloads` | 2 | 300s | 5 | 360s |
| XBRL parser | `redis-xbrl` | `xbrl` | 1 | 900s | 2 | 960s |
| Normalize | `redis-normalize` | `normalize` | 2 | 300s | 3 | 360s |
| Validate | `redis-validate` | `validate` | 2 | 300s | 3 | 360s |
| Publish | `redis-publish` | `publish` | 1 | 120s | 3 | 180s |

The `analytics` and `enrichment` profiles are reserved with process count 0.
They must not be started until an approved business job, contract, and
readiness gate exist.

Start dedicated workers with the following commands:

```bash
php artisan queue:work redis-discovery --queue=discovery --sleep=1 --max-time=3600
php artisan queue:work redis-downloads --queue=downloads --sleep=1 --max-time=3600
php artisan queue:work redis-xbrl --queue=xbrl --sleep=1 --max-time=3600
php artisan queue:work redis-normalize --queue=normalize --sleep=1 --max-time=3600
php artisan queue:work redis-validate --queue=validate --sleep=1 --max-time=3600
php artisan queue:work redis-publish --queue=publish --sleep=1 --max-time=3600
```

The XBRL worker is intentionally limited to one process because parser work is
CPU/memory intensive. Process counts belong to the process manager or
deployment environment; do not add global `--tries` or `--timeout` values that
override job policy.

For local troubleshooting only, use the combined fallback with explicit
priority:

```bash
php artisan queue:work redis-xbrl --queue=publish,validate,normalize,xbrl,downloads,discovery,analytics,enrichment --sleep=1 --max-time=3600
```

This mode can starve queues later in the list and removes workload isolation.
Dedicated workers are the normal operating mode.

## Graceful restart and lifecycle

```bash
php artisan queue:restart
```

The signal is stored in Redis. Each worker finishes its current job, exits, and
must be started again by the process manager or operator. The process-manager
stop/grace period must be greater than 960 seconds so a valid XBRL attempt is
not cut off during restart.

Do not use `queue:listen`; the canonical worker command is `queue:work`.

## Diagnosis and recovery

Inspect queue failures and application status without editing Redis payloads:

```bash
php artisan queue:failed
php artisan financial-data:status FIL-123
```

For a transient infrastructure failure with unchanged logical inputs and
dependency versions, retry the failed UUID:

```bash
php artisan queue:retry <failed-job-uuid>
```

Queue retry preserves the same FilingId, PipelineRun, CorrelationId,
idempotency key, and logical inputs. It does not create a new domain run and
does not delete failed-job or audit history.

Use domain reprocess when an input or dependency changes:

| Change | Reprocess from |
|---|---|
| Source artifact changed | `download` or `parse` |
| Parser or parser configuration changed | `parse` |
| Mapping/normalization changed | `normalize` |
| Validation rule set changed | `validate` |
| Approval or publish contract changed | `publish` |

Example:

```bash
php artisan financial-data:reprocess FIL-123 parse --reason="Parser version changed" --actor=operator-1
```

Domain reprocess creates a new PipelineRun and CorrelationId while retaining
the same FilingId and all immutable upstream/history records. Deterministic
malformed input, invalid contract, unsupported artifact, and blocking domain
errors must be corrected before retry or reprocess; they must not create a
retry storm.

Structured logs and failure context contain FilingId, PipelineRun, stage, job,
connection, queue, attempt, CorrelationId, and relevant versions. Credentials,
tokens, authorization headers, signed URLs, and sensitive paths are redacted.

## Queue-name cutover from Phase 5

Legacy names map as follows:

| Legacy | Canonical |
|---|---|
| `filing-discovery` | `discovery` |
| `filing-download` | `downloads` |
| `filing-parse` | `xbrl` |
| `filing-normalize` | `normalize` |
| `filing-validate` | `validate` |
| `filing-publish` | `publish` |

Cutover checklist:

1. Pause new discovery and reprocess dispatch.
2. Inventory queued, reserved, and failed jobs on all legacy queues.
3. Drain active legacy queues with compatible legacy workers when possible.
4. Deploy the canonical code and configuration.
5. Run config tests before starting canonical workers.
6. Start dedicated canonical workers and process a representative sandbox
   Filing.
7. Verify queue, connection, PipelineRun, and CorrelationId metadata.
8. Retry or re-dispatch legacy failures through Laravel/application commands.
9. Confirm legacy queues are empty before permanently stopping legacy workers.

Never edit serialized Redis payloads manually. Retry and re-dispatch must pass
through the Laravel/application boundary so validation, audit, and idempotency
remain active.

Rollback checklist:

1. Pause new dispatch.
2. Restore compatible legacy code and queue configuration.
3. Restart legacy workers only while serialized payload compatibility is
   guaranteed.
4. Do not delete, overwrite, or roll back domain data or audit history.
5. Re-run the canonical cutover after the underlying issue is corrected.
