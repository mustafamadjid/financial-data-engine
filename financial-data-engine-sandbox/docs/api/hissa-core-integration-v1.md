# HISSA Core Integration API v1

Status: draft external contract hissa.financial-data.integration@0.1.0. This is a sandbox handoff, not a production-readiness claim.

## Local enablement

Copy the non-secret names from .env.example and set a local-only Bearer credential:

    HISSA_INTEGRATION_ENABLED=true
    HISSA_INTEGRATION_CONTRACT=hissa.financial-data.integration
    HISSA_INTEGRATION_CONTRACT_VERSION=0.1.0
    HISSA_INTEGRATION_TOKEN=local-only-token
    HISSA_INTEGRATION_IDENTITY_LABEL=hissa-core-sandbox
    HISSA_INTEGRATION_RATE_LIMIT_PER_MINUTE=60

Do not configure production credentials, production databases, or production source URLs in this sandbox.

## Endpoints

All four endpoints require Authorization: Bearer token and are read-only:

    GET /api/v1/filings/{filing_id}
    GET /api/v1/snapshots/{snapshot_id}
    GET /api/v1/issuers/{issuer_code}/filings
    GET /api/v1/filings/{filing_id}/export

The filing endpoint selects the newest immutable snapshot for that exact filing identity. The snapshot endpoint always addresses the requested historical snapshot. A revision uses a new filing_id and may point to its predecessor with supersedes_filing_id.

## Listing and cursors

The issuer endpoint accepts report_type, fiscal_year, fiscal_period, period_end, published_after, limit, and opaque cursor. limit defaults to 25 and must be between 1 and 100. Results are latest-per-filing, ordered by published_at DESC, snapshot_id DESC. Reuse the returned cursor only with the same filters.

List items contain identity, revision, quality summary, timestamp, and detail/export links. They do not contain facts.

## JSON, precision, and limitations

Detail and snapshot responses contain api_version=v1, external contract identity/version, snapshot and filing identity, VERIFIED quality, canonical facts, provenance, and captured limitations. Export returns exactly the same canonical UTF-8 JSON bytes as detail with Content-Type application/json and a deterministic safe attachment filename.

Monetary and decimal values are base-10 JSON strings. null means unavailable and never zero. Facts are canonical mapped facts only; an unmapped source concept is disclosed under limitations.unmapped_concepts and never invented as a canonical fact. Limitations are captured at publish time and are not recalculated during reads.

## Conditional GET and headers

Successful document responses include ETag, X-Request-ID, and X-HISSA-Contract-Version. Send If-None-Match with the strong ETag to receive 304 and an empty body. Detail, exact snapshot, and export for the same snapshot share the ETag.

## Errors and diagnosis

| HTTP | Code | Meaning |
|---:|---|---|
| 401 | UNAUTHENTICATED | Missing/invalid Bearer credential or disabled integration |
| 404 | PUBLISHED_FILING_NOT_FOUND | No published filing for the exact filing ID |
| 404 | PUBLISHED_SNAPSHOT_NOT_FOUND | Snapshot ID does not exist |
| 422 | INVALID_QUERY | Invalid filter, cursor, or limit |
| 429 | RATE_LIMITED | Identity exceeded 60 requests/minute |
| 500 | INTEGRATION_CONTRACT_VIOLATION | Stored snapshot failed safe contract validation |
| 500 | INTERNAL_ERROR | Unexpected server failure |

Every error includes meta.request_id and X-Request-ID. Server logs use the request ID, route, status, latency, integration identity label, and safe filing/snapshot identifiers. Authorization headers, token values, financial bodies, SQL, stack traces, and absolute paths are not logged or returned.

## Version policy

The URL remains /api/v1 while this external contract is draft 0.1.0. Production enablement is blocked until the freeze checklist is approved. Promotion to 1.0.0 requires atomic schema IDs, examples, config default, response-header tests, mock-client fixtures, changelog, and complete suite updates. Breaking HTTP behavior requires /api/v2; internal contracts/v1 and hissa.financial-data.publish@1.0.0 remain separate.
