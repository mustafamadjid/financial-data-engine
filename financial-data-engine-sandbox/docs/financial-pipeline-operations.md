# Financial pipeline operations runbook

## Inspecting and running workers

Use one worker per stage when isolating an operational issue:

```bash
php artisan queue:work redis --queue=filing-discovery
php artisan queue:work redis --queue=filing-download
php artisan queue:work redis --queue=filing-parse
php artisan queue:work redis --queue=filing-normalize
php artisan queue:work redis --queue=filing-validate
php artisan queue:work redis --queue=filing-publish
```

The worker process must use the same application configuration as the producer. Keep
parser, mapping, validation, and publish contract versions in the deployment
configuration; never place credentials in job arguments or logs.

Check a filing without changing state:

```bash
php artisan financial-data:status FIL-123
```

## Queue retry versus domain reprocess

Queue retry repeats the same queued job attempt and preserves its original
idempotency key and dependency versions. Use it for transient infrastructure
failures after checking the error metadata:

```bash
php artisan queue:failed
php artisan queue:retry <failed-job-id>
```

`queue:retry` does not recalculate facts, mappings, validation results, or a
published snapshot. It must not be used to apply a changed business rule.

Domain reprocess starts a new `PipelineRun`, records the actor/reason and
dependency versions, protects against an overlapping run for the same filing and
stage, and dispatches the selected stage:

```bash
php artisan financial-data:reprocess FIL-123 normalize --reason="Mapping rule v2 approved" --actor=operator-1
php artisan financial-data:reprocess FIL-123 validate --reason="Validation rule v2 approved" --actor=operator-1
```

Allowed stages are `download`, `parse`, `normalize`, `validate`, and `publish`.
The command requires a non-empty reason and validates prerequisites before
dispatching. Raw artifacts, raw facts, and prior validation/publish versions are
retained as history.

## Failure handling

When retries are exhausted, the pipeline records the failed job, marks the latest
run and filing as failed, emits `filing.failed`, and does not dispatch downstream
work. Inspect `queue:failed`, `financial-data:status`, and the job error metadata
before deciding between a queue retry and a domain reprocess.

Structured logs include filing, run, stage, job, queue, attempt, correlation, and
dependency-version context. Source authorization values, tokens, passwords, and
credential-like query parameters are redacted.
