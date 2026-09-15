# HISSA Ops Review Workspaces Task 11–14 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete manual-review marking, enforce the fail-closed Debt Review release gate, reconcile the existing Ops pages for responsive accessibility, and record fresh final verification evidence.

**Architecture:** Manual review is an additive application action over the existing immutable financial entities and `ReviewItem` persistence. Debt Review shares one release-gate decision between page and API routes and remains disabled until all approved prerequisites exist. Visual work reuses the current Ops shell/primitives and does not change data contracts.

**Tech Stack:** Laravel/PHP, Pest, Eloquent, Inertia Vue 3, TypeScript strict mode, TanStack Query, Vitest, Vite, Bun, Tailwind CSS.

**Spec:** `.specs/hissa-ops-review-workspaces/task.md` and the approved design in `git show HEAD:financial-data-engine-sandbox/docs/superpowers/specs/2026-09-11-manual-review-debt-gated-reconciliation-design.md`.

## Global Constraints

- Preserve all existing user changes, including the Task 10 Data Quality work and existing document deletions.
- Do not edit existing migrations, `/api/v1` contracts, source/derived financial records, or historical data.
- Ops endpoints remain public; use domain validation and `system` as the audit actor.
- Debt Review must fail closed and must not invent DA-4 classification or coverage semantics.
- Keep decimal values as strings, timestamps as ISO values, explicit nulls, feature-owned services, TanStack Query server state, and lazy page loading.

---

### Task 1: Manual review backend contract

**Files:**
- Create: `financial-data-engine-sandbox/database/migrations/2026_09_15_110000_add_idempotency_key_to_review_items.php`
- Modify: `financial-data-engine-sandbox/app/Models/ReviewItem.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/Review/MarkReviewItem.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/Review/ReviewMutationException.php`
- Create: `financial-data-engine-sandbox/app/Http/Requests/Ops/MarkReviewItemRequest.php`
- Create: `financial-data-engine-sandbox/app/Http/Controllers/Ops/MarkReviewItemController.php`
- Modify: `financial-data-engine-sandbox/routes/web.php`
- Modify: Financial Review and Data Quality query DTOs
- Test: `financial-data-engine-sandbox/tests/Feature/Ops/ReviewItemMutationTest.php`

**Interfaces:** `POST /ops/actions/review-items` accepts `entity_type`, `entity_id`, `filing_id`, `expected_version`, `rationale`, and `idempotency_key`; it returns a stable review-item DTO or a stable machine-readable error.

- [ ] Write tests first for allow-list, ownership, stale version, idempotent repeat, conflict, reopen, audit, rollback, and immutable source/derived rows.
- [ ] Run the focused test and observe the expected missing-contract failure.
- [ ] Add the forward-only idempotency field and model casts/fillable fields.
- [ ] Implement transactional entity resolution, ownership/version checks, active identity locking, idempotency, audit, and immutable-record guarantees.
- [ ] Add the request/controller/route and stable response codes.
- [ ] Add review-version/capability fields to Financial Review and Data Quality DTOs.
- [ ] Run focused Pest tests and Pint; keep unrelated dirty files unstaged.

### Task 2: Manual review frontend flow

**Files:**
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/types/reviewItem.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/api/reviewItemService.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/queries/reviewItemQueryKeys.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/queries/useMarkReviewItem.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/components/MarkReviewDialog.vue`
- Modify: Financial Review/Data Quality detail dialogs and pages
- Test: `financial-data-engine-sandbox/tests/frontend/reviewItems.test.ts`

- [ ] Write failing service, mutation, validation, pending, error-recovery, and scoped-invalidation tests.
- [ ] Implement the typed service, query keys, mutation, and reusable dialog with focus/ARIA behavior.
- [ ] Integrate the action into Financial Review and Data Quality detail panels without changing existing evidence/reprocess flows.
- [ ] Run focused Vitest and strict TypeScript.

### Task 3: Debt Review release gate

**Files:**
- Modify: `financial-data-engine-sandbox/config/financial-pipeline.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/DebtReview/DebtReviewReleaseGate.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/DebtReview/DebtReviewGateException.php`
- Create: guarded Debt Review page/data controllers
- Modify: `financial-data-engine-sandbox/routes/web.php`
- Test: `financial-data-engine-sandbox/tests/Feature/Ops/DebtReviewGateTest.php`

- [ ] Write failing tests for default-disabled configuration, each missing prerequisite, and every page/list/detail/coverage route.
- [ ] Implement explicit false/null defaults and require enable flag, DA-4 rule version, dictionary version, evidence resolution, pipeline stability, and page approval.
- [ ] Return the same `DEBT_REVIEW_DISABLED` contract before any debt query; expose no debt records while the gate is disabled.
- [ ] Run gate tests, route inspection, and Pint.

### Task 4: Visual reconciliation and final verification

**Files:**
- Modify: existing Ops layout/primitives and Financial Review, Concept Mapping, Data Quality pages only where tests/evidence require it.
- Create: `financial-data-engine-sandbox/docs/ops/task-13-visual-reconciliation.md`
- Modify: `.superpowers/sdd/hissa-ops-review-workspaces/progress.md`

- [ ] Add failing structural/accessibility assertions for landmarks, active state, dialogs, live messages, table semantics, 40px targets, and mobile navigation.
- [ ] Reconcile desktop/tablet/mobile behavior using existing tokens and Figma references `20:113`, `20:198`, `20:283`, and `28:49`; document intentional adaptations.
- [ ] Run focused and full frontend tests, strict TypeScript, production build, full backend/contract tests, route/manifest inspection, query-scope checks, and `git diff --check`.
- [ ] Update the progress ledger only with command-backed evidence; leave Debt Review read tasks gated if prerequisites remain absent.

