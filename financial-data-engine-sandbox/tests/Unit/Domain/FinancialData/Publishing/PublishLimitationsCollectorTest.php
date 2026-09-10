<?php

use App\Domain\FinancialData\Publishing\PublishLimitationsCollector;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\ValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('collects only version-matched non-blocking disclosures deterministically', function (): void {
    $filing = Filing::query()->create([
        'filing_id' => 'FIL-LIMITATIONS',
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/FIL-LIMITATIONS',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'hash',
        'revision_number' => 1,
        'processing_stage' => 'VALIDATED',
        'quality_status' => 'VERIFIED',
    ]);

    ValidationResult::query()->create([
        'validation_result_id' => 'VR-OLD',
        'filing_id' => $filing->filing_id,
        'normalized_dataset_version' => 'dataset-old',
        'validation_rule_set_version' => 'rules-current',
        'rule_code' => 'RULE-OLD',
        'rule_version' => 1,
        'severity' => 'WARN',
        'result' => 'FAIL',
        'message' => 'Old run.',
        'checked_at' => now(),
    ]);
    ValidationResult::query()->create([
        'validation_result_id' => 'VR-CURRENT',
        'filing_id' => $filing->filing_id,
        'normalized_dataset_version' => 'dataset-current',
        'validation_rule_set_version' => 'rules-current',
        'rule_code' => 'RULE-CURRENT',
        'rule_version' => 1,
        'severity' => 'WARN',
        'result' => 'FAIL',
        'message' => 'Current run.',
        'checked_at' => now(),
    ]);
    AuditLog::query()->create([
        'action' => 'normalization.review_required',
        'entity_type' => 'NormalizedFact',
        'entity_id' => 'NF-CURRENT',
        'new_value' => ['normalization_version' => 'dataset-current'],
        'rationale' => 'Unmapped source.',
        'filing_id' => $filing->filing_id,
    ]);
    AuditLog::query()->create([
        'action' => 'normalization.review_required',
        'entity_type' => 'NormalizedFact',
        'entity_id' => 'NF-OLD',
        'new_value' => ['normalization_version' => 'dataset-old'],
        'rationale' => 'Old unmapped source.',
        'filing_id' => $filing->filing_id,
    ]);

    $limitations = (new PublishLimitationsCollector)->collect(
        $filing,
        'dataset-current',
        'rules-current',
    );

    expect($limitations['items'])->toHaveCount(1)
        ->and($limitations['items'][0]['message'])->toBe('Current run.')
        ->and($limitations['unmapped_concepts'])->toHaveCount(1)
        ->and($limitations['unmapped_concepts'][0]['source_reference'])->toBe('NF-CURRENT');
});
