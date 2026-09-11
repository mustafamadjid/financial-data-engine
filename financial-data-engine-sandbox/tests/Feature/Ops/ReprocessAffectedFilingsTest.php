<?php

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineRun;
use App\Models\PublishedSnapshot;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;

uses(DatabaseMigrations::class);

it('accepts the fresh affected set and dispatches one normalize job per filing after commit', function (): void {
    Bus::fake();
    [$series, $filings] = batchReprocessFixture();

    $response = $this->postJson("/ops/actions/concept-mappings/{$series}/reprocess", [
        'filing_ids' => $filings,
        'expected_mapping_set_version' => 2,
        'reason' => 'Apply the reviewed mapping version to affected filings.',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.mappingSeriesKey', $series)
        ->assertJsonPath('data.mappingSetVersion', 2)
        ->assertJsonPath('data.acceptedCount', 2)
        ->assertJsonCount(2, 'data.runs');

    expect(PipelineRun::query()->where('trigger', 'REPROCESS')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'concept_mapping.reprocess_requested')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'concept_mapping.reprocess_requested')->pluck('correlation_id')->unique()->count())->toBe(2);

    Bus::assertDispatched(NormalizeFactsJob::class, 2);
    expect(Filing::query()->whereIn('filing_id', $filings)->pluck('processing_stage')->unique()->all())->toBe(['NORMALIZING']);
});

it('rejects a stale mapping set without creating runs or dispatching jobs', function (): void {
    Bus::fake();
    [$series, $filings] = batchReprocessFixture();

    $this->postJson("/ops/actions/concept-mappings/{$series}/reprocess", [
        'filing_ids' => $filings,
        'expected_mapping_set_version' => 1,
        'reason' => 'Stale mapping set.',
    ])->assertStatus(409)->assertJsonPath('code', 'MAPPING_SET_VERSION_STALE');

    expect(PipelineRun::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'concept_mapping.reprocess_requested')->count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('rejects a filing outside the fresh impact set as an all-or-nothing batch', function (): void {
    Bus::fake();
    [$series, $filings] = batchReprocessFixture();
    $unaffected = Filing::query()->create([
        'filing_id' => 'FIL-OUTSIDE', 'issuer_code' => 'HSSC', 'fiscal_year' => 2025, 'fiscal_period' => 'FY',
        'period_end' => '2025-12-31', 'source_url' => 'https://example.test/outside', 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => str_repeat('z', 64), 'revision_number' => 1,
    ]);

    $this->postJson("/ops/actions/concept-mappings/{$series}/reprocess", [
        'filing_ids' => [$filings[0], $unaffected->filing_id],
        'expected_mapping_set_version' => 2,
        'reason' => 'Contains an ineligible filing.',
    ])->assertStatus(409)->assertJsonPath('code', 'FILING_OUTSIDE_IMPACT');

    expect(PipelineRun::query()->count())->toBe(0)
        ->and(Filing::query()->whereIn('filing_id', $filings)->where('processing_stage', 'NORMALIZING')->count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('rejects duplicate filing ids and an overlapping active run before changing the batch', function (): void {
    Bus::fake();
    [$series, $filings] = batchReprocessFixture();

    $this->postJson("/ops/actions/concept-mappings/{$series}/reprocess", [
        'filing_ids' => [$filings[0], $filings[0]],
        'expected_mapping_set_version' => 2,
        'reason' => 'Duplicate selection.',
    ])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_FILING_IDS');

    PipelineRun::query()->create([
        'filing_id' => $filings[0], 'trigger' => 'REPROCESS', 'started_from_stage' => 'NORMALIZE',
        'status' => 'RUNNING', 'correlation_id' => '11111111-1111-1111-1111-111111111111', 'started_at' => now(),
    ]);

    $this->postJson("/ops/actions/concept-mappings/{$series}/reprocess", [
        'filing_ids' => $filings,
        'expected_mapping_set_version' => 2,
        'reason' => 'Overlapping selection.',
    ])->assertStatus(409)->assertJsonPath('code', 'ACTIVE_OPERATION');

    expect(PipelineRun::query()->count())->toBe(1);
    Bus::assertNothingDispatched();
});

it('preserves raw facts, prior normalized facts, validation results, and published snapshots', function (): void {
    Bus::fake();
    [$series, $filings] = batchReprocessFixture();
    $filing = Filing::query()->findOrFail($filings[0]);
    $rawCount = RawFact::query()->where('filing_id', $filing->filing_id)->count();
    NormalizedFact::query()->create([
        'normalized_fact_id' => 'NF-PRIOR', 'filing_id' => $filing->filing_id, 'raw_fact_id' => 'RAW-BATCH-1',
        'issuer_code' => $filing->issuer_code, 'period' => 'FY', 'period_end' => '2025-12-31',
        'canonical_concept' => 'total_assets', 'value' => '123.45', 'currency' => 'USD', 'scope' => 'CONSOLIDATED',
        'data_type' => 'REPORTED', 'source_concept' => 'Assets', 'mapping_rule_id' => 'MAP-BATCH-1',
        'mapping_rule_version' => 1, 'normalization_version' => '1.0.0@1', 'normalization_status' => 'NORMALIZED', 'validation_status' => 'VERIFIED',
    ]);
    ValidationResult::query()->create([
        'validation_result_id' => 'VAL-PRIOR', 'filing_id' => $filing->filing_id, 'normalized_fact_ids' => ['NF-PRIOR'],
        'rule_code' => 'ASSET_CHECK', 'rule_version' => 1, 'result' => 'PASS', 'severity' => 'INFO', 'checked_at' => now(),
    ]);
    PublishedSnapshot::query()->create([
        'snapshot_id' => 'SNAP-PRIOR', 'filing_id' => $filing->filing_id, 'revision_number' => 1,
        'publish_contract_version' => '1.0.0', 'normalized_dataset_version' => '1.0.0@1',
        'validation_rule_set_version' => '1.0.0', 'publish_idempotency_key' => 'snap-prior', 'payload' => [], 'lineage' => [], 'published_at' => now(),
    ]);

    $this->postJson("/ops/actions/concept-mappings/{$series}/reprocess", [
        'filing_ids' => [$filing->filing_id], 'expected_mapping_set_version' => 2, 'reason' => 'Preserve history while recalculating.',
    ])->assertStatus(202);

    expect(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe($rawCount)
        ->and(NormalizedFact::query()->where('normalized_fact_id', 'NF-PRIOR')->exists())->toBeTrue()
        ->and(ValidationResult::query()->where('validation_result_id', 'VAL-PRIOR')->exists())->toBeTrue()
        ->and(PublishedSnapshot::query()->where('snapshot_id', 'SNAP-PRIOR')->exists())->toBeTrue();
});

/** @return array{0: string, 1: list<string>} */
function batchReprocessFixture(): array
{
    $series = MappingSeriesKey::from('Assets', 'general');
    foreach (['FIL-BATCH-1', 'FIL-BATCH-2'] as $index => $filingId) {
        Filing::query()->create([
            'filing_id' => $filingId, 'issuer_code' => 'HSS'.($index + 1), 'fiscal_year' => 2025, 'fiscal_period' => 'FY',
            'period_end' => '2025-12-31', 'source_url' => "https://example.test/{$filingId}", 'source_type' => 'XBRL_INSTANCE',
            'source_hash' => str_repeat((string) ($index + 1), 64), 'revision_number' => 1, 'taxonomy_entry_point' => 'general',
            'processing_stage' => 'NORMALIZED', 'quality_status' => 'VERIFIED',
        ]);
        $contextId = "CTX-BATCH-{$index}";
        XbrlContext::query()->create([
            'context_id' => $contextId, 'filing_id' => $filingId, 'source_context_id' => "ctx-{$index}",
            'entity_identifier' => 'HSS'.($index + 1), 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT',
            'instant_date' => '2025-12-31', 'context_status' => 'RESOLVED',
        ]);
        RawFact::query()->create([
            'raw_fact_id' => "RAW-BATCH-{$index}", 'filing_id' => $filingId, 'source_concept' => 'Assets',
            'raw_value' => (string) ($index + 1), 'context_ref' => $contextId, 'is_nil' => false, 'fact_status' => 'EXTRACTED',
        ]);
    }
    CanonicalConcept::query()->create([
        'code' => 'total_assets', 'name' => 'Total assets', 'statement' => 'BALANCE_SHEET', 'period_type' => 'INSTANT',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-BATCH-1', 'mapping_series_key' => $series, 'source_concept' => 'Assets', 'entry_point' => 'general',
        'canonical_concept' => 'total_assets', 'status' => 'APPROVED', 'rule_version' => 1, 'rationale' => 'Initial',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-BATCH-2', 'mapping_series_key' => $series, 'source_concept' => 'Assets', 'entry_point' => 'general',
        'canonical_concept' => 'total_assets', 'status' => 'APPROVED', 'rule_version' => 2, 'rationale' => 'Reviewed', 'supersedes_mapping_rule_id' => 'MAP-BATCH-1',
    ]);

    return [$series, ['FIL-BATCH-1', 'FIL-BATCH-2']];
}
