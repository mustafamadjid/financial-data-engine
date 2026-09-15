# Product

<!-- impeccable:product-schema 1 -->
<!-- This record is based on the user's product brief and repository evidence. Inferred statements are marked explicitly. -->

## Platform

web

## Stack

Laravel with Inertia.js, Vue 3, TypeScript, Tailwind CSS, and TanStack Vue Query for frontend server-state behavior. The repository uses Vite and Bun for frontend tooling.

## Users

The primary users are:

- Technical operators monitoring and recovering financial-data processing.
- Financial-data analysts reviewing normalized facts, validation outcomes, and mappings.
- Internal reviewers examining evidence, lineage, and review-required conditions.

They use the product while operating or reviewing a financial-data processing pipeline and need to scan state quickly, investigate exceptions, and take controlled follow-up actions.

## Product Purpose

HISSA Financial Data Engine is an internal operations and review workspace for a financial-data processing pipeline. It helps users monitor filing processing, inspect pipeline status, identify failures, retry or reprocess filings, review data-quality validation issues, inspect normalized financial facts, and manage concept mappings with their history and impact.

Success means that an operator or reviewer can understand what happened to a filing or fact, see the relevant status and lineage, and take the correct next action without losing context.

## Positioning

Inferred from the supplied brief and repository structure: the product's distinct value is the combination of pipeline execution operations and financial-data review in one workflow, joining filing state, failure recovery, validation outcomes, normalized facts, and versioned concept-mapping impact.

## Operating Context

- This is an internal browser application, not a marketing or public-facing website.
- The primary operations workspaces are `/ops/pipeline`, `/ops/financial-review`, `/ops/data-quality`, `/ops/concept-mappings`, and the currently deferred `/ops/debt-review` route.
- The canonical processing flow is Discovery → Download → Parse → Normalize → Validate → Publish.
- Operators distinguish retrying the same job attempt from reprocessing a filing from an explicit stage after a relevant state change.
- Review workflows depend on source lineage, immutable upstream evidence, versioned mappings, versioned validation rules, and explicit status values.
- The internal operations UI and the public `/api/v1` published-data API are separate delivery concerns and must remain so.

## Capabilities and Constraints

Confirmed capabilities and constraints:

- Monitor filing lists and pipeline summaries, inspect filing details, history, and artifacts.
- Identify failed or blocked stages and invoke retry or reprocess actions through existing application flows.
- Search, filter, sort, paginate, and inspect normalized financial facts and their source lineage.
- Review validation results and summaries, inspect linked input facts, and hand off to pipeline recovery workflows.
- Browse concept mappings and canonical options; inspect mapping history and impact; create mapping versions; and reprocess affected filings.
- Preserve business logic, API contracts, Vue Query behavior, routes, backend behavior, and domain boundaries during UI redesign.
- Prioritize operational clarity, fast scanning, information density, comparison, status visibility, predictable interactions, accessibility, error visibility, and efficient table workflows.
- Keep unknown, unmapped, failed, and review-required states explicit. Do not infer financial or sharia classifications.
- Preserve the contract-first boundary in `contracts/v1`, the Laravel application boundary, and the Python/XBRL worker boundary.

Open or intentionally undecided facts:

- Authentication, authorization roles, and deployment-specific access policy are not established in the supplied brief.
- The `/ops/debt-review` route exists, but the current navigation marks it as not yet available; its delivery scope and timing remain open.

## Brand Commitments

- The product is identified in the existing application as HISSA Ops / HISSA Financial Data Engine.
- The interface should communicate an internal, utilitarian operations workspace and must not adopt marketing-site conventions.
- No additional logo, palette, typography, or campaign asset requirements were established by the brief.

## Evidence on Hand

- Product and UX brief supplied in the request.
- Laravel/Inertia application under `financial-data-engine-sandbox/`.
- Ops pages under `financial-data-engine-sandbox/resources/js/Pages/` and reusable operations components under `resources/js/components/ops/`.
- Feature boundaries for pipeline, data quality, financial review, concept mapping, and debt review under `resources/js/features/`.
- Route definitions in `financial-data-engine-sandbox/routes/web.php` and public API definitions in `routes/api.php`.
- Versioned data and API contracts under `contracts/v1/` and `contracts/api/v1/`.
- Automated frontend, Laravel, Python, and contract tests in the repository.
- No customer testimonials, public proof, marketing claims, or external brand assets are established; future UI work must not fabricate them.

## Product Principles

1. Make pipeline state and blockers immediately legible.
2. Make recovery actions deliberate, contextual, and distinguish retry from reprocess.
3. Preserve lineage, version context, and auditability across review workflows.
4. Support dense comparison and table-based work without sacrificing readability or accessibility.
5. Keep uncertain, invalid, and review-required states explicit rather than silently normalizing them.

## Accessibility & Inclusion

Accessibility is a product requirement. Preserve and extend keyboard access, visible focus, skip navigation, semantic tables, status announcements, dialog focus management, and clear error and empty states. Do not rely on color alone to communicate status. No specific conformance level was established in the brief.
