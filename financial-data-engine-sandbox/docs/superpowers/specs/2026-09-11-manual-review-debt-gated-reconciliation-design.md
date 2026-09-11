# Task 11-14: Manual Review, Gated Debt Review, and Final Reconciliation

## Scope and status

This design covers the remaining work in tasks 11-14 of the HISSA Ops Review Workspaces plan.

The implementation will:

- complete manual review marking for the existing Financial Review and Data Quality detail flows;
- add and enforce a disabled-by-default Debt Review release gate;
- reconcile the existing Financial Review, Concept Mapping, and Data Quality pages against the approved Figma nodes and responsive behavior;
- execute the final verification and traceability audit without claiming gated work is complete.

Debt Review remains read-only and unavailable until the DA-4 dictionary/formula contract, evidence prerequisites, pipeline stability evidence, and page-specific design approval are present. No financial classification or coverage rule will be inferred to bypass that gate.

Existing uncommitted user changes, especially the Task 10 Data Quality changes, are out of scope and must be preserved.

## Existing contracts and constraints

- All Ops endpoints are public by approved product decision; capabilities express domain eligibility rather than identity authorization.
- The audit actor defaults to `system` because the application has no identity model.
- `review_items` and its lifecycle fields already exist from the shared persistence foundation.
- Review lifecycle is `OPEN`, `RESOLVED`, and `REOPENED`; active duplicate review items are idempotent and there is no assignment workflow.
- `UNMAPPED` remains a read projection only.
- Source facts, normalized facts, validation results, published snapshots, and historical derived data remain immutable.
- Decimal values are serialized as strings, timestamps as ISO-8601 values, nulls remain explicit, and existing `/api/v1` contracts must not change.
- Frontend server state remains in TanStack Query; feature services remain independent of Vue; no Pinia is introduced.
- Existing Ops shell, tokens, async states, semantic tables, dialogs, query-key conventions, and lazy page loading are reused.

## Task 11 design: manual review marking

### Backend flow

Introduce a `MarkReviewItem` application service and a typed Ops action endpoint. The request accepts:

- an allow-listed entity type and entity identifier;
- the filing identifier, when required by the entity ownership check;
- an expected version representing the current source/detail version;
- a rationale;
- an idempotency key.

The service will:

1. validate the entity type against the supported detail entities (`normalized_fact` and `validation_result`);
2. resolve the entity and verify that it belongs to the requested filing;
3. verify the expected version against the current entity version, including the version-scoped validation execution for validation results;
4. lock the relevant review identity and check for an existing active item;
5. return the existing active item for an equivalent idempotent request, or reject conflicting stale/duplicate input with a stable error;
6. create or reopen a review item inside one transaction;
7. write an audit record containing the before/after lifecycle state, rationale, actor, filing, and correlation identity;
8. never update the source or derived financial entity.

Active uniqueness is represented through the existing `active_identity` constraint. Resolved records clear their active identity so that a later marking creates a new lifecycle item while preserving history. The endpoint returns the review item and capability state using the existing Ops response/error conventions.

### Frontend flow

Add a feature-owned review-items service, types, query keys, mutation composable, and one reusable async dialog. Financial Review and Data Quality detail dialogs receive typed review capability and entity context, emit a typed open/close/accepted event, and show:

- explicit disabled reasons;
- rationale validation;
- submitting and accepted states;
- duplicate-submit protection;
- recoverable error state that preserves entered rationale;
- a clear notice that marking review does not mutate the financial record or reprocess data.

On a successful mutation, only the relevant detail/list queries are invalidated. Mutation retries are disabled unless the user explicitly submits again after the error state is shown.

### Task 11 tests

Backend tests cover entity allow-list, cross-filing ownership rejection, stale expected version, first mark, idempotent repeat, conflicting duplicate, reopen after resolution, audit before/after values, transaction rollback, and immutability of source/derived rows. Frontend tests cover disabled reasons, validation, loading, duplicate-submit prevention, recoverable errors, accepted state, and targeted query invalidation.

## Task 12 design: gated Debt Review

### Release gate

Introduce a `DebtReviewReleaseGate` with a single authoritative decision used by both navigation/page routing and data endpoints. The gate is disabled by default and is enabled only when all mandatory prerequisites are explicitly configured and verified:

- approved DA-4 rule/dictionary versions;
- evidence resolution capability;
- pipeline stability evidence;
- page-specific design approval.

When disabled, the page route and every Debt Review API route return the same stable feature-disabled response. Hidden navigation is not treated as enforcement. The gate response exposes a non-sensitive reason code and does not leak debt records.

### Deferred read experience

The server-side debt list/detail query and coverage calculator, Debt Review page, and related lifecycle tests are not implemented in this cycle while the gate prerequisites remain unresolved. Existing DebtRecord model/type files remain untouched unless required to implement the gate boundary. Once the gate is approved, the read-only slice must preserve decimal strings, explicit availability states, evidence capabilities, stable pagination, and `UNDETERMINED` coverage when evidence is insufficient; unavailable coverage must never render as `100%`.

## Task 13 design: responsive/accessibility reconciliation

Reconcile the existing pages with the approved Figma nodes:

- Financial Review: `20:113`;
- Concept Mapping: `20:198`;
- Data Quality: `20:283`;
- responsive behavior: `28:49`.

The reconciliation uses existing `OpsLayout` and visual primitives. It covers desktop shell dimensions, sidebar/header/content spacing, typography, table columns, selected states, dialogs, colors, and state messaging. Intentional repository-driven differences are recorded in the implementation notes or verification output rather than silently left unexplained.

Responsive behavior will be checked at small-desktop, tablet, and mobile widths. Navigation collapses into the existing overlay pattern, tables retain usable horizontal overflow and keyboard selection, evidence/detail panels remain accessible, persistent actions remain reachable, and complex mutations are not made mobile-hostile.

Accessibility checks cover semantic table structure, non-color status meaning, minimum 40px interactive targets, dialog focus trap/Escape/focus return, label/error associations, live async status, keyboard navigation, and WCAG AA token combinations. Debt Review is excluded from visual reconciliation until its release gate and page design are approved.

## Task 14 design: verification and traceability

Run the narrowest affected tests during implementation and then the final verification set:

- changed-file Pint;
- focused and full Pest suites;
- frontend unit tests;
- strict TypeScript check;
- production Vite build;
- route inspection and `/api/v1` contract regression;
- representative query-count/profile checks for fact, mapping, and validation lists;
- `git diff --check`;
- production manifest inspection for lazy pages and justified async dialogs/drawers.

The traceability audit maps requirements to the approved design and task items, confirms all open decisions are either resolved or explicitly gated, checks that no external contract, private storage path, secret, production connection, or historical data was changed, and reports Debt Review as gated if prerequisites remain absent.

## Error handling and observability

All new actions use stable machine-readable error codes and HTTP semantics already used by Ops. Validation and ownership failures are deterministic and do not disclose unrelated filing data. Correlation identifiers are retained in audit records and response metadata where existing conventions support them. No mutation dispatches pipeline work.

## Rollout and fallback

Manual review marking is additive and can be disabled through its capability response without changing source data. Debt Review is safe to deploy while disabled because both its route and API enforce the release gate. If visual reconciliation exposes a regression, revert only the affected presentation/component change; no data migration rollback or historical overwrite is required.

## Out of scope

- authentication or identity/assignment workflows;
- changing external `/api/v1` contracts;
- editing existing migrations;
- Debt Review classification rules or DA-4 assumptions;
- enabling Debt Review without prerequisite approval;
- source/derived financial record mutation;
- unrelated refactors or dependency additions.
