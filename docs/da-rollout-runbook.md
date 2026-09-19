# DA-1-3 Alignment Rollout Runbook

Status: release procedure prepared; production execution requires explicit owner approval.

This runbook covers the contract-v2, DA artifact, normalization, validation, and publish cutover. It is intentionally operational: it does not fetch external source artifacts or reprocess production data by itself.

## Release prerequisites

The release manager must record the following approvals in the change ticket before the cutover:

| Gate | Required evidence | Owner |
| --- | --- | --- |
| Contract | v2 schemas reviewed; v1 remains read-only/historical | Contract owner |
| Availability | `AVAILABLE`, `NIL`, `MISSING`, `NOT_APPLICABLE`, `UNKNOWN` vocabulary approved | Data owner |
| DA artifacts | DA-2 hash/version and DA-3 rule-set version reviewed; all mappings remain `PROPOSED` until approval | Finance/data reviewer |
| Sources | official source URL, publication timestamp, artifact license/access policy | Legal/data owner |
| Production action | explicit authorization for source fetching and bulk reprocessing | CTO/operations |

No production command in this document is an authorization. The operator must attach the approval reference before running it.

## 1. Pre-deploy backup and safety checks

1. Freeze the release candidate commit and record its SHA-256, PHP/Python versions, worker version, contract versions, DA artifact hashes, normalization version, and validation rule-set version.
2. Confirm database backup and restore test for the target database. Keep the backup immutable and outside the application workspace.
3. Confirm queue drain/maintenance window, worker capacity, disk headroom for immutable raw/parser artifacts, and log retention.
4. Run from repository root:

   ```text
   python scripts/verify_da.py
   ```

   The command must finish with `DA alignment verification: PASS` before deployment.
5. Confirm `.env.example` contains placeholders only. Production secrets must come from the secret manager, never from source control or this runbook.

## 2. Ordered deployment

Deploy in this order, stopping on the first failed gate:

1. Deploy the Laravel code and forward-only migrations. Run `php artisan migrate --force` only after the backup gate passes; never down-migrate after v2/raw writes.
2. Deploy the worker package with the pinned parser and Arelle versions. Keep the v1 reader enabled for historical payloads.
3. Configure the v2 writer in the worker and Laravel parser boundary atomically:
   - `parser_contract_version=2.0.0`;
   - source element IDs and taxonomy metadata required;
   - v1 accepted only for historical/rollback reads.
4. Run the DA importer in validation-only mode:

   ```text
   php artisan financial-data:import-da "<repo>/DA-1-3" DA-2-v1 --dry-run
   ```

   Confirm the artifact hash and row counts match the approved change ticket. A dry-run must not create or update concepts, mappings, or import ledger rows.
5. After reviewer approval, import the exact same hash/version without `--dry-run`. Verify `PROPOSED` source mappings become `DRAFT`, never `APPROVED`.
6. Seed/activate the reviewed validation rule set. The mandatory 20-code manifest must pass before any filing can become `VERIFIED`.
7. Enable normalization and validation queues. Keep publishing disabled until the canary gate passes.

## 3. Canary and parity gate

Run a non-production canary first, then a production canary limited to explicitly approved filings.

Required comparison dimensions:

- parser contract status and source hash;
- raw fact/context/unit/dimension counts and duplicate IDs;
- source namespace, source element ID, taxonomy metadata;
- normalized fact count, availability statuses, mapping/rule versions;
- quality distribution and limitation codes;
- publish payload hash and lineage IDs.

The DA pilot reference is **40 filings: 32 `VERIFIED`, 8 `REVIEW_REQUIRED`, 0 `FAILED`**. CPIN/PWON cash warnings and TLKM restatement warnings must match the reviewed expected identities. A mismatch blocks bulk reprocessing and publishing.

Run the source-map report before canary approval:

```text
php artisan financial-data:source-map-report "<repo>/DA-1-3/DA-1/data/extracted/source_map.csv"
```

`PARTIAL_SOURCE_IDENTITY` rows remain review work; they are not silently promoted to official source evidence.

## 4. Rollback

Rollback is an application/configuration rollback, not a destructive data rollback:

1. Stop new parsing, normalization, validation, and publishing dispatches.
2. Preserve v2 raw facts, parser outputs, audit rows, validation results, and snapshots for investigation.
3. Roll application configuration back to v1 read-only/historical parsing and disable the v2 writer.
4. Do not delete v2 records and do not run reverse migrations after production writes.
5. Reconcile any already-published snapshot using its immutable snapshot ID; never overwrite it in place.
6. Open a corrective change ticket with the failing comparison, source hash, rule-set version, and operator/correlation IDs before retrying.

## 5. Monitoring and incident thresholds

Monitor these counters by release, parser version, mapping version, normalization version, and rule-set version:

- parser contract terminal failures and retryable worker failures;
- unmapped and `REVIEW_REQUIRED` normalization counts;
- `NIL`, `MISSING`, `UNKNOWN`, and `NOT_APPLICABLE` availability counts;
- validation `VERIFIED`, `REVIEW_REQUIRED`, and `FAILED` distribution;
- rule completeness failures and missing mandatory rule executions;
- publish rejections, mixed-version lineage errors, and limitation-code counts;
- queue latency, batch duration/count, disk usage, and database lock/dead-letter rates.

Page the on-call owner for any `FAILED` increase, missing mandatory rule, source-hash mismatch, duplicate-ID rejection, mixed-version publish attempt, or unexpected quality-distribution drift. Keep payload values out of logs; use IDs, counts, hashes, and correlation IDs only.

## 6. Post-release closeout

1. Rerun `python scripts/verify_da.py` against the release commit.
2. Attach compact counts/hashes/status summaries and the canary comparison to the change ticket.
3. Record reviewer approval, operator identity, timestamps, and rollback decision window.
4. Only after sign-off, enable bulk reprocessing and publishing for the approved scope.
