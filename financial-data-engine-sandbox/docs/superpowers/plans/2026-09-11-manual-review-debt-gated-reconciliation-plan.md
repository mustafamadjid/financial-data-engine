# Manual Review, Gated Debt Review, and Final Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete manual review marking, enforce the unresolved Debt Review release gate, reconcile the existing Ops pages with the approved Figma references, and run the final traceability verification without bypassing gated requirements.

**Architecture:** Add manual review as a small application action over the existing `ReviewItem` persistence and immutable financial entities. Add a fail-closed `DebtReviewReleaseGate` shared by page/API controllers; do not implement debt classification while DA-4 remains unresolved. Reuse the existing Ops shell, tokens, TanStack Query services, semantic table, dialog, and async-state primitives for the visual/accessibility pass.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Eloquent, SQLite test database, Inertia Vue 3, TypeScript strict mode, TanStack Query, Vite, Bun, Tailwind CSS, Figma MCP references.

**Spec:** `financial-data-engine-sandbox/docs/superpowers/specs/2026-09-11-manual-review-debt-gated-reconciliation-design.md`

## Global Constraints

- Preserve all existing uncommitted Task 10 changes; do not reset, restore, or overwrite unrelated files.
- All Ops endpoints remain public by product decision; domain capabilities and validation enforce eligibility.
- Use `system` as the audit actor; do not add authentication or assignment behavior.
- Use forward-only migrations; never edit existing migrations.
- Do not mutate raw facts, normalized facts, validation results, published snapshots, or historical derived results.
- Serialize decimal values as strings, timestamps as ISO-8601 values, and unknown values as explicit `null`/unknown states.
- Do not change external `/api/v1` routes, schemas, fixtures, or response contracts.
- Keep frontend server state in TanStack Query, services independent of Vue, and TypeScript free of `any`.
- Reuse `OpsLayout`, `AsyncState`, `DataTable`, `OpsDialog`, `StatusBadge`, existing tokens, and existing query-key conventions.
- Debt Review must remain unavailable unless every release prerequisite is explicitly true; do not infer DA-4 rules, aliases, evidence, or coverage formulas.
- Run `vendor/bin/pint --dirty --format agent` after every PHP change batch and before completion.

## File Map

### Manual review

- Create `financial-data-engine-sandbox/database/migrations/2026_09_11_110000_add_idempotency_key_to_review_items.php` for the missing forward-only idempotency field and index.
- Modify `financial-data-engine-sandbox/app/Models/ReviewItem.php` to cast lifecycle timestamps and expose the new idempotency field.
- Create `financial-data-engine-sandbox/app/Application/Ops/Review/ReviewMutationException.php` for stable domain errors.
- Create `financial-data-engine-sandbox/app/Application/Ops/Review/MarkReviewItem.php` for locking, ownership/version checks, idempotency, lifecycle creation, and audit.
- Create `financial-data-engine-sandbox/app/Http/Requests/Ops/MarkReviewItemRequest.php` for public request validation and JSON validation errors.
- Create `financial-data-engine-sandbox/app/Http/Controllers/Ops/MarkReviewItemController.php` for the action endpoint and DTO.
- Modify `financial-data-engine-sandbox/routes/web.php` to register the action route.
- Modify `financial-data-engine-sandbox/app/Application/Ops/FinancialReview/FinancialFactQuery.php` and `app/Application/Ops/DataQuality/ValidationQualityQuery.php` to expose review version tokens and capabilities.
- Modify `financial-data-engine-sandbox/resources/js/features/financial-review/types/financialReview.ts` and `features/data-quality/types/dataQuality.ts` for review metadata.
- Create `financial-data-engine-sandbox/resources/js/features/review-items/types/reviewItem.ts`, `api/reviewItemService.ts`, `queries/reviewItemQueryKeys.ts`, `queries/useMarkReviewItem.ts`, and `components/MarkReviewDialog.vue`.
- Modify `resources/js/features/financial-review/components/FinancialFactDetailDialog.vue`, `features/data-quality/components/ValidationDetailDialog.vue`, and the two page components to mount the reusable dialog.
- Create `financial-data-engine-sandbox/tests/Feature/Ops/ReviewItemMutationTest.php` and `tests/frontend/reviewItems.test.ts`.

### Debt gate

- Modify `financial-data-engine-sandbox/config/financial-pipeline.php` with fail-closed `debt_review` prerequisite configuration.
- Create `financial-data-engine-sandbox/app/Application/Ops/DebtReview/DebtReviewReleaseGate.php` and `DebtReviewGateException.php`.
- Create `financial-data-engine-sandbox/app/Http/Controllers/Ops/DebtReviewPageController.php` and `DebtReviewDataController.php`.
- Modify `financial-data-engine-sandbox/routes/web.php` with the page, list, detail, and coverage routes guarded by the gate.
- Create `financial-data-engine-sandbox/tests/Feature/Ops/DebtReviewGateTest.php`.
- Do not add Debt Review classification, coverage calculation, or page implementation while the DA-4 gate is unresolved.

### Visual and accessibility reconciliation

- Modify `financial-data-engine-sandbox/resources/js/layouts/OpsLayout.vue`, shared Ops primitives, and the Financial Review, Concept Mapping, and Data Quality pages only where the Figma comparison identifies a mismatch.
- Modify `financial-data-engine-sandbox/resources/css/app.css` only for repository-owned tokens required by the approved references.
- Add or update focused frontend tests under `tests/frontend/ops`, `tests/frontend/financial-review`, `tests/frontend/concept-mapping`, and `tests/frontend/data-quality`.
- Create `financial-data-engine-sandbox/docs/ops/task-13-visual-reconciliation.md` with node references, verified states, and intentional repository-driven adaptations.

### Final verification

- Update `.superpowers/sdd/hissa-ops-review-workspaces/progress.md` only after implementation and verification evidence exists.
- Do not create verification scripts when existing Pest/Vitest/build commands cover the behavior.

---

### Task 1: Add the manual-review persistence gap and domain action

**Files:**
- Create: `financial-data-engine-sandbox/database/migrations/2026_09_11_110000_add_idempotency_key_to_review_items.php`
- Modify: `financial-data-engine-sandbox/app/Models/ReviewItem.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/Review/ReviewMutationException.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/Review/MarkReviewItem.php`
- Test: `financial-data-engine-sandbox/tests/Feature/Ops/ReviewItemMutationTest.php`

**Interfaces:**
- `MarkReviewItem::execute(string $entityType, string $entityId, string $filingId, string $expectedVersion, string $rationale, string $idempotencyKey): ReviewItem`
- `ReviewMutationException` exposes `errorCode`, `status`, and optional `field` in the same style as `MappingMutationException`.
- `ReviewItem` stores `idempotency_key`, `active_identity`, `expected_version`, lifecycle status, and audit actor fields.

- [ ] **Step 1: Write failing migration/model tests**

Add assertions that a migrated `review_items` table accepts `idempotency_key`, that the model persists it, and that a second active identity cannot be inserted. Use `DatabaseMigrations` and query the schema through the model, not raw application code.

```php
it('persists a review idempotency key and keeps active identities unique', function (): void {
    $filing = reviewFilingFixture();

    ReviewItem::query()->create([
        'review_item_id' => 'REV-001',
        'entity_type' => 'normalized_fact',
        'entity_id' => 'NF-001',
        'filing_id' => $filing->filing_id,
        'status' => 'OPEN',
        'rationale' => 'Inspect the unmapped value.',
        'idempotency_key' => 'idem-001',
        'active_identity' => 'normalized_fact:NF-001',
        'expected_version' => 'norm-v1:map-v1',
        'created_by' => 'system',
    ]);

    expect(ReviewItem::query()->firstOrFail()->idempotency_key)->toBe('idem-001');
});
```

- [ ] **Step 2: Run the new test and verify it fails**

Run from `financial-data-engine-sandbox`:

```powershell
php artisan test --compact tests/Feature/Ops/ReviewItemMutationTest.php --filter="persists a review idempotency key"
```

Expected: FAIL because the current foundation migration/model has no `idempotency_key` field.

- [ ] **Step 3: Add the forward-only migration and model field**

Create the migration with a nullable string column for compatibility with any pre-existing rows and a unique index on non-null idempotency keys. Update the model fillable list and datetime casts. Do not edit `2026_09_11_100000_add_review_workspace_foundations.php`.

```php
Schema::table('review_items', function (Blueprint $table): void {
    $table->string('idempotency_key', 255)->nullable()->after('expected_version');
    $table->unique('idempotency_key', 'review_items_idempotency_key_unique');
});
```

- [ ] **Step 4: Write failing action tests for the domain invariants**

Add tests for:

1. normalized fact marking with `OPEN`, `system`, active identity, and audit;
2. validation result marking with its version-scoped token;
3. cross-filing ownership rejection;
4. unsupported entity rejection;
5. stale expected version rejection;
6. same idempotency key returning the existing item without a second audit;
7. same entity with a different key returning an active-duplicate conflict;
8. resolved item reopening as `REOPENED` while preserving the original row history;
9. transaction rollback when audit insertion fails;
10. unchanged `NormalizedFact` and `ValidationResult` records.

Use deterministic fixtures with `NormalizedFact`, `ValidationResult`, `Filing`, and `AuditLog`; assert error codes and status codes, not exception text alone.

```php
it('marks a normalized fact idempotently without mutating the fact', function (): void {
    $fact = normalizedFactFixture();
    $before = $fact->getAttributes();

    $payload = reviewMarkPayload($fact->filing_id, 'normalized_fact', $fact->normalized_fact_id, 'norm-v1:map-v1', 'idem-fact-001');

    $this->postJson('/ops/actions/review-items', $payload)->assertCreated()
        ->assertJsonPath('data.status', 'OPEN')
        ->assertJsonPath('data.createdBy', 'system');

    $this->postJson('/ops/actions/review-items', $payload)->assertOk()
        ->assertJsonPath('data.reviewItemId', 'REV-'.md5('idem-fact-001'));

    expect($fact->fresh()->getAttributes())->toBe($before)
        ->and(ReviewItem::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'review_item.marked')->count())->toBe(1);
});
```

- [ ] **Step 5: Run the failing action tests**

```powershell
php artisan test --compact tests/Feature/Ops/ReviewItemMutationTest.php
```

Expected: FAIL because the action service and endpoint do not exist.

- [ ] **Step 6: Implement the minimal `MarkReviewItem` transaction**

Use `DB::transaction()` and lock the entity plus the active review identity. Recompute the server-side version token before comparing `expectedVersion`:

- normalized fact: `normalization_version: mapping_rule_version`;
- validation result: `normalized_dataset_version:validation_rule_set_version:rule_version`.

Build `active_identity` as `<entityType>:<entityId>`, store `idempotency_key`, and create an audit row with `old_value` and `new_value` lifecycle snapshots. Return the existing row only when the idempotency key, entity, filing, and expected version all match. Map conflicts to `ReviewMutationException` with these stable codes: `REVIEW_ENTITY_NOT_ALLOWED`, `REVIEW_ENTITY_NOT_FOUND`, `REVIEW_OWNERSHIP_MISMATCH`, `REVIEW_VERSION_STALE`, `REVIEW_IDEMPOTENCY_CONFLICT`, and `REVIEW_ALREADY_ACTIVE`.

- [ ] **Step 7: Run the action tests and format PHP**

```powershell
php artisan test --compact tests/Feature/Ops/ReviewItemMutationTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all manual-review backend tests pass and Pint reports no remaining formatting changes.

- [ ] **Step 8: Commit the backend review action**

```powershell
git add database/migrations/2026_09_11_110000_add_idempotency_key_to_review_items.php app/Models/ReviewItem.php app/Application/Ops/Review tests/Feature/Ops/ReviewItemMutationTest.php
git commit -m "feat: add idempotent manual review marking"
```

Run this from `financial-data-engine-sandbox`; do not stage unrelated Task 10 files.

### Task 2: Expose the manual-review endpoint and version-aware DTOs

**Files:**
- Create: `financial-data-engine-sandbox/app/Http/Requests/Ops/MarkReviewItemRequest.php`
- Create: `financial-data-engine-sandbox/app/Http/Controllers/Ops/MarkReviewItemController.php`
- Modify: `financial-data-engine-sandbox/routes/web.php`
- Modify: `financial-data-engine-sandbox/app/Application/Ops/FinancialReview/FinancialFactQuery.php`
- Modify: `financial-data-engine-sandbox/app/Application/Ops/DataQuality/ValidationQualityQuery.php`
- Modify: `financial-data-engine-sandbox/resources/js/features/financial-review/types/financialReview.ts`
- Modify: `financial-data-engine-sandbox/resources/js/features/data-quality/types/dataQuality.ts`
- Test: `financial-data-engine-sandbox/tests/Feature/Ops/ReviewItemMutationTest.php`

**Interfaces:**
- POST `/ops/actions/review-items` accepts `entity_type`, `entity_id`, `filing_id`, `expected_version`, `rationale`, and `idempotency_key`.
- Response is `{ data: { reviewItemId, entityType, entityId, filingId, status, rationale, expectedVersion, createdBy, createdAt, active } }`.
- Financial detail DTO exposes `reviewVersion` and `allowedActions.markForReview`.
- Validation list/detail DTO exposes `reviewVersion` and the same capability shape.

- [ ] **Step 1: Add request tests for validation and stable JSON errors**

Assert required fields, allowed entity types, max rationale length, non-empty idempotency key, and the existing `VALIDATION_ERROR` envelope. Keep `authorize(): bool` returning `true` because Ops access is public by approved product decision.

- [ ] **Step 2: Run the request tests and verify failure**

```powershell
php artisan test --compact tests/Feature/Ops/ReviewItemMutationTest.php --filter="validation"
```

Expected: FAIL because the route/request/controller are not registered.

- [ ] **Step 3: Implement request, controller, and route**

Follow `CreateMappingVersionRequest` and `CreateMappingVersionController` conventions. Catch `ReviewMutationException`, return its code/message/field errors, return `201` for a newly created item and `200` for an idempotent existing item, and never serialize storage paths or model internals.

```php
Route::post('/actions/review-items', MarkReviewItemController::class)
    ->name('actions.review-items.store');
```

- [ ] **Step 4: Add review version tokens to query DTOs**

In `FinancialFactQuery::item()`, add `reviewVersion` using the normalization and mapping versions. In `ValidationQualityQuery`, add `reviewVersion` using dataset version, rule-set version, and rule version. Ensure detail queries return exactly the same token as list rows. Set `markForReview.allowed` to `false` with a deterministic reason when the entity is missing an ownership/version prerequisite.

- [ ] **Step 5: Update feature TypeScript types and backend assertions**

Add `reviewVersion: string`, `ReviewCapability`, and `ReviewItemResponse` types. Extend the Financial Review and Data Quality feature tests to assert the token is present and stable across list/detail responses.

- [ ] **Step 6: Run focused backend tests and format**

```powershell
php artisan test --compact tests/Feature/Ops/ReviewItemMutationTest.php tests/Feature/Ops/FinancialReviewTest.php tests/Feature/Ops/DataQualityTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit the endpoint and DTO contract**

```powershell
git add app/Http/Requests/Ops/MarkReviewItemRequest.php app/Http/Controllers/Ops/MarkReviewItemController.php routes/web.php app/Application/Ops/FinancialReview/FinancialFactQuery.php app/Application/Ops/DataQuality/ValidationQualityQuery.php resources/js/features/financial-review/types/financialReview.ts resources/js/features/data-quality/types/dataQuality.ts tests/Feature/Ops/ReviewItemMutationTest.php
git commit -m "feat: expose manual review action contract"
```

### Task 3: Build and integrate the reusable manual-review UI

**Files:**
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/types/reviewItem.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/api/reviewItemService.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/queries/reviewItemQueryKeys.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/queries/useMarkReviewItem.ts`
- Create: `financial-data-engine-sandbox/resources/js/features/review-items/components/MarkReviewDialog.vue`
- Modify: `financial-data-engine-sandbox/resources/js/features/financial-review/components/FinancialFactDetailDialog.vue`
- Modify: `financial-data-engine-sandbox/resources/js/features/data-quality/components/ValidationDetailDialog.vue`
- Modify: `financial-data-engine-sandbox/resources/js/Pages/FinancialReview/Index.vue`
- Modify: `financial-data-engine-sandbox/resources/js/Pages/DataQuality/Index.vue`
- Test: `financial-data-engine-sandbox/tests/frontend/reviewItems.test.ts`

**Interfaces:**
- `markReviewItem(payload: MarkReviewItemPayload): Promise<ReviewItemResponse>` is a Vue-independent service function.
- `useMarkReviewItem()` exposes TanStack mutation state and invalidates only the relevant Financial Review/Data Quality keys after success.
- `MarkReviewDialog` accepts `open`, `entityType`, `entityId`, `filingId`, `expectedVersion`, `capability`, and emits `close` and `accepted` with typed payloads.

- [ ] **Step 1: Write failing service/mutation tests**

Mock `fetch` and assert the service sends `POST /ops/actions/review-items` with the exact payload, decodes the response, surfaces the stable error code, and prevents a second request while pending. Assert successful mutation invalidation is scoped to the selected entity and list/summary keys.

- [ ] **Step 2: Run the frontend tests and verify failure**

```powershell
bunx vitest run tests/frontend/reviewItems.test.ts
```

Expected: FAIL because the feature package does not exist.

- [ ] **Step 3: Implement the service, query key, and mutation composable**

Reuse `requestOps`, `isRecord`, and the existing error decoder. Keep the mutation disabled on a capability with `allowed === false`; pass the server-provided `expectedVersion` unchanged; never generate a client-side version.

- [ ] **Step 4: Implement the async dialog**

Use `OpsDialog` and `AsyncState`. The dialog must:

- show the disabled reason without a submit button when capability is false;
- require a non-blank rationale;
- disable submit during the mutation;
- keep the rationale after a recoverable error;
- expose `aria-live="polite"` for submission/accepted status;
- close on Escape through `OpsDialog` and return focus to the invoking button.

- [ ] **Step 5: Integrate both detail panels**

Add a Mark for review action to Financial Review and Data Quality detail panels. Pass the entity type and server-provided version token from the selected row/detail. Close the dialog only on success; keep all existing evidence, mapping, validation, and reprocess navigation unchanged.

- [ ] **Step 6: Run frontend tests and strict TypeScript**

```powershell
bunx vitest run tests/frontend/reviewItems.test.ts tests/frontend/financial-review tests/frontend/data-quality tests/frontend/ops
bunx tsc --noEmit -p tsconfig.json
```

Expected: all focused tests pass and TypeScript exits with code 0.

- [ ] **Step 7: Commit the UI slice**

```powershell
git add resources/js/features/review-items resources/js/features/financial-review resources/js/features/data-quality resources/js/Pages/FinancialReview/Index.vue resources/js/Pages/DataQuality/Index.vue tests/frontend/reviewItems.test.ts
git commit -m "feat: add manual review marking UI"
```

### Task 4: Implement the fail-closed Debt Review release gate

**Files:**
- Modify: `financial-data-engine-sandbox/config/financial-pipeline.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/DebtReview/DebtReviewReleaseGate.php`
- Create: `financial-data-engine-sandbox/app/Application/Ops/DebtReview/DebtReviewGateException.php`
- Create: `financial-data-engine-sandbox/app/Http/Controllers/Ops/DebtReviewPageController.php`
- Create: `financial-data-engine-sandbox/app/Http/Controllers/Ops/DebtReviewDataController.php`
- Modify: `financial-data-engine-sandbox/routes/web.php`
- Test: `financial-data-engine-sandbox/tests/Feature/Ops/DebtReviewGateTest.php`

**Interfaces:**
- `DebtReviewReleaseGate::enabled(): bool` returns true only when the explicit enable flag and all prerequisites are true.
- `DebtReviewReleaseGate::assertEnabled(): void` throws `DebtReviewGateException('DEBT_REVIEW_DISABLED', ...)` when disabled.
- Page/API controllers call the same gate before rendering or reading any `DebtRecord`.

- [ ] **Step 1: Write failing gate tests**

Cover the default configuration, each missing prerequisite, all-prerequisite success, page route, list route, detail route, and coverage route. Assert disabled routes return the stable `DEBT_REVIEW_DISABLED` code and do not query `debt_records`.

```php
it('fails closed for every debt route by default', function (string $uri): void {
    $this->getJson($uri)->assertNotFound()
        ->assertJsonPath('code', 'DEBT_REVIEW_DISABLED');
})->with([
    '/ops/debt-review',
    '/ops/data/debt-records?filing_id=FIL-001',
    '/ops/data/debt-records/DEBT-001?filing_id=FIL-001',
    '/ops/data/debt-coverage?filing_id=FIL-001',
]);
```

- [ ] **Step 2: Run the gate tests and verify failure**

```powershell
php artisan test --compact tests/Feature/Ops/DebtReviewGateTest.php
```

Expected: FAIL because the gate routes/controllers do not exist.

- [ ] **Step 3: Add explicit fail-closed configuration**

Add this shape to `config/financial-pipeline.php`; every value defaults to false/null:

```php
'debt_review' => [
    'enabled' => env('FINANCIAL_PIPELINE_DEBT_REVIEW_ENABLED', false),
    'approved_da4_rule_version' => env('FINANCIAL_PIPELINE_DEBT_REVIEW_DA4_RULE_VERSION'),
    'approved_dictionary_version' => env('FINANCIAL_PIPELINE_DEBT_REVIEW_DICTIONARY_VERSION'),
    'evidence_resolution' => env('FINANCIAL_PIPELINE_DEBT_REVIEW_EVIDENCE_RESOLUTION', false),
    'pipeline_stable' => env('FINANCIAL_PIPELINE_DEBT_REVIEW_PIPELINE_STABLE', false),
    'page_design_approved' => env('FINANCIAL_PIPELINE_DEBT_REVIEW_PAGE_DESIGN_APPROVED', false),
],
```

The gate must require non-empty DA-4/dictionary versions and strict boolean true for the other prerequisites.

- [ ] **Step 4: Implement the gate and guarded controllers/routes**

Return HTTP 404 with `{ code: 'DEBT_REVIEW_DISABLED', message: 'Debt Review is not released.' }` for disabled page/API access. When enabled, controllers may render the future `DebtReview/Index` page or return a controlled empty contract, but this cycle must not query or expose debt records. Register all routes before parameterized routes that could otherwise intercept them.

- [ ] **Step 5: Run tests, inspect routes, and format**

```powershell
php artisan test --compact tests/Feature/Ops/DebtReviewGateTest.php
php artisan route:list --path=ops
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit the gate**

```powershell
git add config/financial-pipeline.php app/Application/Ops/DebtReview app/Http/Controllers/Ops/DebtReviewPageController.php app/Http/Controllers/Ops/DebtReviewDataController.php routes/web.php tests/Feature/Ops/DebtReviewGateTest.php
git commit -m "feat: enforce gated debt review release"
```

### Task 5: Reconcile existing Ops pages against Figma and accessibility requirements

**Files:**
- Modify: `financial-data-engine-sandbox/resources/js/layouts/OpsLayout.vue`
- Modify: `financial-data-engine-sandbox/resources/js/components/ops/AsyncState.vue`
- Modify: `financial-data-engine-sandbox/resources/js/components/ops/DataTable.vue`
- Modify: `financial-data-engine-sandbox/resources/js/components/ops/OpsDialog.vue`
- Modify: `financial-data-engine-sandbox/resources/js/components/ops/StatusBadge.vue`
- Modify: `financial-data-engine-sandbox/resources/js/Pages/FinancialReview/Index.vue`
- Modify: `financial-data-engine-sandbox/resources/js/Pages/ConceptMapping/Index.vue`
- Modify: `financial-data-engine-sandbox/resources/js/Pages/DataQuality/Index.vue`
- Modify: `financial-data-engine-sandbox/resources/css/app.css`
- Create: `financial-data-engine-sandbox/docs/ops/task-13-visual-reconciliation.md`
- Test: focused existing page/layout tests plus new accessibility assertions under `financial-data-engine-sandbox/tests/frontend/ops`

**References:**
- Financial Review Figma node `20:113`.
- Concept Mapping Figma node `20:198`.
- Data Quality Figma node `20:283`.
- Responsive Figma node `28:49`.

**Interfaces:** Preserve existing page props, route names, query services, and component events. Only visual/layout/accessibility behavior changes are allowed in this task.

- [ ] **Step 1: Capture baseline screenshots and test state**

Run the existing frontend suite and start the local app using the repository's normal dev command. Capture desktop, tablet, and mobile screenshots for all three available pages. Record current shell width, content padding, table minimum widths, dialog behavior, and visible request states in the reconciliation document.

- [ ] **Step 2: Write failing structural/accessibility assertions**

Add tests for:

- one semantic navigation landmark and one main landmark;
- active route state exposed beyond color;
- all dialog close controls with accessible names;
- focus return after closing a dialog;
- `aria-live` on refresh/error/accepted messages;
- table captions and header associations;
- interactive targets retaining at least `min-h-10`/40px sizing;
- mobile navigation overlay and Escape behavior.

- [ ] **Step 3: Run the new assertions and verify failures**

```powershell
bunx vitest run tests/frontend/ops
```

Expected: only newly introduced mismatch assertions fail; unrelated Task 10 tests must remain unchanged and passing.

- [ ] **Step 4: Reconcile the shared shell and primitives**

Use existing tokens and components to match the Figma shell: desktop sidebar/content split, 64px header, 24px content rhythm, 232px sidebar target, active navigation surface, responsive collapsed/overlay navigation, keyboard focus ring, and status meaning independent of color. Keep all page routes and query behavior unchanged.

- [ ] **Step 5: Reconcile page-specific layout and states**

Adjust only the three available pages to match their Figma references: filter card hierarchy, selected-row state, table column rhythm, detail dialog spacing, empty/loading/error/refresh copy placement, and persistent action placement. Keep Financial Review and Data Quality review actions read-only except for the explicit manual-review action from Tasks 1-3. Do not add Debt Review visuals while its gate is disabled.

- [ ] **Step 6: Verify responsive and keyboard behavior**

Use the browser/local app at desktop, small-desktop, tablet, and mobile widths. Verify collapsed/overlay navigation, horizontal table overflow, selectable rows, evidence/detail dialogs, persistent actions, Escape, focus trap, and focus return. Update the reconciliation document with each intentional adaptation and the reason it is repository-driven.

- [ ] **Step 7: Run focused tests and build**

```powershell
bunx vitest run tests/frontend/ops tests/frontend/financial-review tests/frontend/concept-mapping tests/frontend/data-quality
bunx tsc --noEmit -p tsconfig.json
bun run build
```

- [ ] **Step 8: Commit the reconciliation**

```powershell
git add resources/js/layouts/OpsLayout.vue resources/js/components/ops resources/js/Pages/FinancialReview/Index.vue resources/js/Pages/ConceptMapping/Index.vue resources/js/Pages/DataQuality/Index.vue resources/css/app.css tests/frontend/ops docs/ops/task-13-visual-reconciliation.md
git commit -m "fix: reconcile Ops review layouts and accessibility"
```

### Task 6: Execute final verification and traceability audit

**Files:**
- Modify: `.superpowers/sdd/hissa-ops-review-workspaces/progress.md`
- Read: `financial-data-engine-sandbox/docs/superpowers/specs/2026-09-11-manual-review-debt-gated-reconciliation-design.md`
- Read: `financial-data-engine-sandbox/docs/ops/task-13-visual-reconciliation.md`

**Interfaces:** This task changes the ledger only after command evidence exists. It does not add application behavior.

- [ ] **Step 1: Run changed-file formatting and focused backend tests**

```powershell
Set-Location financial-data-engine-sandbox
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Ops/ReviewItemMutationTest.php tests/Feature/Ops/DebtReviewGateTest.php tests/Feature/Ops/FinancialReviewTest.php tests/Feature/Ops/DataQualityTest.php
```

Expected: exit code 0 with all focused tests passing.

- [ ] **Step 2: Run frontend tests and strict TypeScript**

```powershell
bunx vitest run
bunx tsc --noEmit -p tsconfig.json
```

Expected: exit code 0; no `any` or lazy-page resolution errors.

- [ ] **Step 3: Run full backend and contract regression suites**

```powershell
php artisan test --compact
php artisan test --compact tests/Contract/ApiV1ContractTest.php tests/Feature/Api/V1
```

Expected: all existing pipeline, published API, and contract tests remain green.

- [ ] **Step 4: Inspect routes, build manifest, query scope, and diff safety**

```powershell
php artisan route:list --path=ops
bun run build
git diff --check
git status --short
```

Inspect `public/build/manifest.json` to confirm review pages are lazy-loaded and no unrelated whole-library import appeared. Confirm representative fact/mapping/validation list tests retain bounded query counts; do not add virtual scrolling without measured evidence.

- [ ] **Step 5: Perform the traceability audit**

Check each spec section against the implementation and tests:

- Task 11: mutation, ownership/version, idempotency, lifecycle, audit, UI recovery, and immutability.
- Task 12: gate enforced on page/API and no Debt Review assumptions.
- Task 13: Figma nodes, responsive states, keyboard/focus/contrast, and documented adaptations.
- Task 14: command evidence, lazy manifest, query profile, open decisions, and out-of-scope safety.

Record the actual command results and the explicit Debt Review gate status in the ledger/reconciliation document. Do not mark Task 12.2-12.4 complete while DA-4 and design approval remain absent.

- [ ] **Step 6: Update the progress ledger and commit verification evidence**

Update only the relevant task checkboxes and evidence lines in `.superpowers/sdd/hissa-ops-review-workspaces/progress.md`, preserving its existing format and prior Task 10 evidence. Then run:

```powershell
git add .superpowers/sdd/hissa-ops-review-workspaces/progress.md docs/ops/task-13-visual-reconciliation.md
git commit -m "chore: record review workspace verification"
```

## Self-review checklist

- [x] Spec coverage: manual review, gated Debt Review, visual/accessibility reconciliation, and final verification each have an implementation task.
- [x] No existing migration is edited; the missing idempotency field is added forward-only.
- [x] Source/derived data remains immutable and `/api/v1` remains untouched.
- [x] Debt Review is fail-closed and not implemented with invented DA-4 semantics.
- [x] Type names and method signatures are consistent across backend DTOs and frontend feature types.
- [x] Every task ends with a focused test/build or verification checkpoint and a scoped commit.
- [x] Existing uncommitted Task 10 files are explicitly excluded from staging commands.
