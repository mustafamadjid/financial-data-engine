# HISSA Core Integration API v1 — draft `0.1.0`

This directory is the external integration contract. It is intentionally separate from `contracts/v1`, which remains the internal pipeline contract `hissa.financial-data.publish@1.0.0`.

The draft exposes four authenticated, read-only `GET` routes under `/api/v1`. Successful responses contain only immutable `VERIFIED` `PublishedSnapshot` data. Decimal and monetary values are JSON strings; `null` means unavailable and never zero.

The draft remains disabled outside local/test environments until Contract Freeze. Before promotion to `1.0.0`, update schemas and IDs, fixtures and examples, configuration defaults, response-header tests, the mock consumer, changelog, and rerun the complete PHP/Python compatibility suites atomically. A breaking HTTP contract requires a new `/api/v2` namespace; additive compatible fields may remain on `/api/v1` after a stable major release.

See `openapi.yaml`, the three JSON Schemas, and `fixtures/` for machine-readable examples. The freeze checklist must receive sign-off from the Data Analyst, Full Stack Engineer, HISSA Core owner, and security/network owner before production enablement.

## Version promotion procedure

Promotion from `0.x` to `1.0.0` is one atomic change set: update schema `$id` values and examples, update the configuration default and response-header assertions, add or update mock-client fixtures, record a changelog entry, and rerun all contract, API, consumer-compatibility, and representative E2E tests. Do not silently serve a payload whose body/header version differs from the configured contract version. After `1.0.0`, use a new contract major for breaking field/type/semantic changes; use `/api/v2` when the HTTP behavior is breaking.
