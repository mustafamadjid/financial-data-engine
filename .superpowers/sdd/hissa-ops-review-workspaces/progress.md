# SDD ledger — plan: .specs/hissa-ops-review-workspaces/task.md

## Preflight scan

| Task | Shared interface/file | Finding | Ruling |
|---|---|---|---|
| 1 → 2 | capability matrix, entry point, DTOs | Task 2 schema depends on Task 1 decisions | Public access is resolved; review lifecycle uses append-only/idempotent items; taxonomy entry point remains nullable and parser-owned; mapping mutation is blocked when unknown |
| 2 → 3 | mapping/version DTOs and query keys | Frontend must consume stable DTOs | DTOs use decimal strings, ISO timestamps, explicit nulls, stable pagination and domain capabilities |
| 3 → 4 | OpsLayout and Financial Review page | Page shell must precede vertical slice | Extract shared shell first and keep Pipeline behavior unchanged |
| 4 → 5 | migrations, tests, build | Task 5 is a checkpoint, not new behavior | Run fresh/upgrade migration checks, focused/full PHP tests, frontend tests, strict TypeScript, Pint and production build |
| 1 | product/data gates | All endpoints are public per user approval; no identity authorization | Use `system` audit actor; retain domain validation and feature gates |
| 2 | persistence | Existing migrations must not be edited | Add forward-only migrations and collision-safe backfill |
| 3 | frontend foundation | Existing pipeline page has no shell | Add reusable `OpsLayout`/sidebar and preserve current page behavior |
| 4 | Financial Review | Existing financial tables are read-only source data | Add bounded read-only query path and safe evidence access |
| 5 | checkpoint | Requires all prior contracts to be verified | Block progression if migration or contract tests fail |

## Rulings

- Ruling: public endpoint capabilities are domain eligibility, not identity authorization — this follows the user's explicit decision that every endpoint is public; cost if wrong is exposure of mutation endpoints, mitigated only by domain validation and deployment/network controls.
- Ruling: taxonomy entry point is a nullable parser-owned filing field and unknown legacy values remain unmapped — no authoritative field exists today; cost if wrong is deferred mapping applicability for legacy data rather than silently selecting an incorrect rule.
- Ruling: manual review has OPEN/RESOLVED/REOPENED lifecycle with idempotent active uniqueness and no assignment — the app has no identity model; cost if wrong is later migration of review workflow semantics.
- Ruling: Debt Review remains feature-disabled through this checkpoint — DA-4 dictionary/formula and page approval are not available; cost if wrong is delayed Debt Review exposure, which is safer than inventing financial classification.

## Verification ledger

- Task 1: approved public capability matrix, review lifecycle, parser-owned taxonomy policy, DTO/error contract, and Debt Review gate recorded in this ledger and `.specs/hissa-ops-review-workspaces/task.md`.
- Task 2: forward-only review-workspace migration, mapping-series metadata, parser propagation, deterministic series selection, and `ReviewItem` model added.
- Task 3: `OpsLayout`, responsive navigation, shared async/table/dialog/status primitives, shared Ops HTTP client, feature DTO/query-key foundations, and Pipeline regression integration added.
- Task 4: Financial Review list/detail endpoints and Inertia page added with server pagination/filtering, decimal-string DTOs, source/context/unit/mapping/validation lineage, and lazy detail dialog.
- Task 5 checkpoint evidence: 256 PHP tests / 1,241 assertions, 25 frontend tests, strict TypeScript (`tsc --noEmit`), Pint, production Vite build, route inspection, and `git diff --check` all pass.
- Task 6: Concept Mapping inventory, mapped/unmapped projection, history, canonical options, impact preview, responsive page, and async panels added; focused read tests pass.
- Task 7: append-only `CreateMappingVersion`, expected-version concurrency guard, canonical/evidence validation, audit, mutation dialog, and targeted TanStack invalidation added; mutation tests pass without reprocess dispatch.
- Task 8: `ReprocessAffectedFilings`, fresh impact subset validation, deterministic locks, atomic runs/audits, after-commit normalize dispatch, typed confirmation dialog, and targeted invalidation added; batch safety tests pass.
- Task 9 checkpoint evidence: 267 PHP tests / 1,328 assertions, 26 frontend tests, strict TypeScript, Pint, production Vite build, public Ops route inspection, and `git diff --check` pass.
