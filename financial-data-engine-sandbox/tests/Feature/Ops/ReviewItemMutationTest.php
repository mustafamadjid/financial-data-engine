<?php

use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RawFact;
use App\Models\ReviewItem;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('marks a normalized fact once and makes an identical request idempotent', function (): void {
    $fact = reviewFact('NF-REVIEW-001', 'FIL-REVIEW-001');
    $payload = reviewPayload($fact, 'FIL-REVIEW-001');

    $this->postJson('/ops/actions/review-items', $payload)
        ->assertCreated()
        ->assertJsonPath('data.entityType', 'normalized_fact')
        ->assertJsonPath('data.entityId', 'NF-REVIEW-001')
        ->assertJsonPath('data.status', 'OPEN')
        ->assertJsonPath('data.createdBy', 'system');

    $this->postJson('/ops/actions/review-items', $payload)
        ->assertOk()
        ->assertJsonPath('data.reviewItemId', ReviewItem::query()->value('review_item_id'));

    expect(ReviewItem::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'review_item.marked')->count())->toBe(1)
        ->and($fact->fresh()->value)->toBe('100.250000000000000000');
});

it('rejects a cross-filing entity and a stale review version', function (): void {
    $fact = reviewFact('NF-REVIEW-002', 'FIL-REVIEW-002');

    $this->postJson('/ops/actions/review-items', reviewPayload($fact, 'FIL-REVIEW-OTHER'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'REVIEW_ENTITY_NOT_OWNED');

    $payload = reviewPayload($fact, 'FIL-REVIEW-002');
    $payload['expected_version'] = 'stale-version';

    $this->postJson('/ops/actions/review-items', $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'REVIEW_VERSION_STALE');
});

it('rejects a conflicting active review request without changing the financial fact', function (): void {
    $fact = reviewFact('NF-REVIEW-003', 'FIL-REVIEW-003');
    $payload = reviewPayload($fact, 'FIL-REVIEW-003');

    $this->postJson('/ops/actions/review-items', $payload)->assertCreated();

    $conflict = $payload;
    $conflict['idempotency_key'] = 'review-request-conflict';
    $conflict['rationale'] = 'A different reason';

    $this->postJson('/ops/actions/review-items', $conflict)
        ->assertStatus(409)
        ->assertJsonPath('code', 'REVIEW_ITEM_EXISTS');

    expect(ReviewItem::query()->count())->toBe(1)
        ->and($fact->fresh()->canonical_concept)->toBe('review_total_assets');
});

it('rejects blank rationale and idempotency values at the request boundary', function (): void {
    $response = $this->postJson('/ops/actions/review-items', [
        'entity_type' => 'normalized_fact',
        'entity_id' => 'NF-REVIEW-MISSING',
        'filing_id' => 'FIL-REVIEW-MISSING',
        'expected_version' => 'version-1',
        'rationale' => '   ',
        'idempotency_key' => "\t",
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['fieldErrors' => ['rationale', 'idempotency_key']]);
});

/** @return array<string, string> */
function reviewPayload(NormalizedFact $fact, string $filingId): array
{
    return [
        'entity_type' => 'normalized_fact',
        'entity_id' => $fact->normalized_fact_id,
        'filing_id' => $filingId,
        'expected_version' => '1.0.0@mapping-1|MAP-REVIEW-001|1',
        'rationale' => 'The normalized value requires analyst review.',
        'idempotency_key' => 'review-request-'.$fact->normalized_fact_id,
    ];
}

function reviewFact(string $factId, string $filingId): NormalizedFact
{
    Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'HSSA',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/review',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', $filingId),
        'revision_number' => 1,
    ]);
    CanonicalConcept::query()->create([
        'code' => 'review_total_assets',
        'name' => 'Review total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-REVIEW-001',
        'source_concept' => 'Assets',
        'canonical_concept' => 'review_total_assets',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    XbrlContext::query()->create([
        'context_id' => 'CTX-'.$factId,
        'filing_id' => $filingId,
        'source_context_id' => 'source-'.$factId,
        'entity_identifier' => 'HSSA',
        'period_type' => 'INSTANT',
        'context_status' => 'RESOLVED',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => 'RAW-'.$factId,
        'filing_id' => $filingId,
        'source_concept' => 'Assets',
        'raw_value' => '100.25',
        'normalized_numeric_value' => '100.25',
        'context_ref' => 'CTX-'.$factId,
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
    ]);

    return NormalizedFact::query()->create([
        'normalized_fact_id' => $factId,
        'filing_id' => $filingId,
        'raw_fact_id' => 'RAW-'.$factId,
        'issuer_code' => 'HSSA',
        'period' => 'FY 2025',
        'period_end' => '2025-12-31',
        'canonical_concept' => 'review_total_assets',
        'value' => '100.25',
        'currency' => 'IDR',
        'data_type' => 'MONETARY',
        'source_concept' => 'Assets',
        'mapping_rule_id' => 'MAP-REVIEW-001',
        'mapping_rule_version' => 1,
        'normalization_version' => '1.0.0@mapping-1',
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'VERIFIED',
    ]);
}
