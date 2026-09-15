# Task 13 — Ops visual and accessibility reconciliation

## Reference frames

- Financial Review: Figma node 20:113
- Concept Mapping: Figma node 20:198
- Data Quality: Figma node 20:283
- Responsive behavior: Figma node 28:49

## Reconciled implementation

The shared OpsLayout preserves the approved desktop geometry: a 64px header, 24px content inset from the sm breakpoint, a 232px sidebar at xl desktop width, and a compact 72px sidebar at lg small-desktop widths. The content canvas, surface, border, action, status, and typography tokens are defined in resources/css/app.css using the approved HISSA palette. The three available review pages reuse the same filter-card, table, state, selection, and async-dialog primitives.

All three tables use the reusable DataTable wrapper with a caption and controlled horizontal overflow. The wrapper adds scope="col" to slotted column headers when a page does not provide it explicitly, preserving semantic table behavior across the existing page templates without changing their data contracts.

## Responsive and keyboard behavior

- lg and below use the existing overlay navigation; Escape closes it and returns focus to the menu button.
- xl restores the full 232px navigation labels.
- Review tables retain their minimum readable widths and expose horizontal scrolling rather than clipping financial values or controls.
- Detail dialogs retain the existing focus trap, Escape handling, and focus return; manual-review dialogs preserve rationale after recoverable errors and announce async state changes.
- The shell exposes a skip link, a named nav landmark, a main landmark, and aria-current="page" for the active workspace.
- Status is expressed as text as well as color. Loading, error, empty, disabled, refreshing, and accepted states use explicit live-region semantics.

## Intentional repository-driven adaptations

1. Debt Review remains absent from page-level visual reconciliation because its release gate and complete page-specific design are not approved. Navigation keeps it visible as a disabled Later item, while page and API routes fail closed.
2. The existing pages keep wide tables at smaller widths because preserving decimal precision, lineage identifiers, and action reachability is more important than collapsing financial columns into ambiguous cards.
3. The current repository has no approved local Mona Sans asset; the CSS token keeps Mona Sans first and uses the existing Instrument Sans/system fallback chain.

## Verification evidence

- tests/frontend/ops/OpsLayout.test.ts: overlay focus, Escape, focus return, active route, and extension slots.
- tests/frontend/ops/OpsAccessibility.test.ts: async live regions, captions, and column-header semantics.
- tests/frontend/ops/OpsDialog.test.ts: dialog focus trap, Escape, and return focus.
- Task 11 review dialog tests: disabled reason, rationale flow, and accepted mutation contract.

