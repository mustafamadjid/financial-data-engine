<?php

use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\ValidationRule;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('returns only the explicitly selected validation execution in list and summary', function (): void {
    $filing = qualityFiling('FIL-QUALITY-001', 'VERIFIED');
    qualityRule('BALANCE-001', 1, 'ERROR');
    qualityRule('BALANCE-001', 2, 'ERROR');
    qualityFact('NF-QUALITY-001', $filing->filing_id);
    qualityValidation('VAL-QUALITY-OLD', $filing->filing_id, 'dataset-v1', 'rules-v1', 'BALANCE-001', 1, 'FAIL');
    qualityValidation('VAL-QUALITY-ACTIVE', $filing->filing_id, 'dataset-v2', 'rules-v2', 'BALANCE-001', 2, 'PASS');

    $this->getJson('/ops/data/validation-results?filing_id=FIL-QUALITY-001&dataset_version=dataset-v2&rule_set_version=rules-v2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.validationResultId', 'VAL-QUALITY-ACTIVE')
        ->assertJsonPath('data.0.rule.description', 'Balance validation v2')
        ->assertJsonPath('data.0.reviewVersion', 'dataset-v2|rules-v2|BALANCE-001|2')
        ->assertJsonPath('data.0.allowedActions.markForReview.allowed', true)
        ->assertJsonPath('data.0.inputFacts.0.href', '/ops/financial-review?filing_id=FIL-QUALITY-001&normalized_fact_id=NF-QUALITY-001');

    $this->getJson('/ops/data/validation-summary?filing_id=FIL-QUALITY-001&dataset_version=dataset-v2&rule_set_version=rules-v2')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.byResult.PASS', 1)
        ->assertJsonPath('data.qualityStatus', 'VERIFIED');
});

it('does not expose a validation detail from another execution', function (): void {
    $filing = qualityFiling('FIL-QUALITY-002', 'REVIEW_REQUIRED');
    qualityRule('BALANCE-002', 1, 'WARN');
    qualityFact('NF-QUALITY-002', $filing->filing_id);
    qualityValidation('VAL-QUALITY-MISMATCH', $filing->filing_id, 'dataset-old', 'rules-old', 'BALANCE-002', 1, 'FAIL');

    $this->getJson('/ops/data/validation-results/VAL-QUALITY-MISMATCH?filing_id=FIL-QUALITY-002&dataset_version=dataset-current&rule_set_version=rules-current')
        ->assertNotFound()
        ->assertJsonPath('code', 'VALIDATION_EXECUTION_NOT_FOUND');
});

it('downgrades VERIFIED when the selected execution contains a blocking result', function (): void {
    $filing = qualityFiling('FIL-QUALITY-003', 'VERIFIED');
    qualityRule('BALANCE-003', 1, 'ERROR');
    qualityValidation('VAL-QUALITY-BLOCKING', $filing->filing_id, 'dataset-v3', 'rules-v3', 'BALANCE-003', 1, 'FAIL');

    $this->getJson('/ops/data/validation-summary?filing_id=FIL-QUALITY-003&dataset_version=dataset-v3&rule_set_version=rules-v3')
        ->assertOk()
        ->assertJsonPath('data.qualityStatus', 'FAILED')
        ->assertJsonPath('data.verifiedInvariant', false);
});

function qualityFiling(string $id, string $qualityStatus): Filing
{
    return Filing::query()->create([
        'filing_id' => $id,
        'issuer_code' => 'HSSA',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/quality',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', $id),
        'revision_number' => 1,
        'quality_status' => $qualityStatus,
    ]);
}

function qualityRule(string $code, int $version, string $severity): ValidationRule
{
    return ValidationRule::query()->create([
        'rule_code' => $code,
        'description' => "Balance validation v{$version}",
        'severity' => $severity,
        'rule_version' => $version,
        'enabled' => true,
    ]);
}

function qualityFact(string $id, string $filingId): NormalizedFact
{
    CanonicalConcept::query()->create([
        'code' => 'quality_total_assets',
        'name' => 'Quality total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'QUALITY-MAPPING',
        'source_concept' => 'Assets',
        'canonical_concept' => 'quality_total_assets',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    XbrlContext::query()->create([
        'context_id' => "CTX-{$id}",
        'filing_id' => $filingId,
        'source_context_id' => "source-{$id}",
        'entity_identifier' => 'HSSA',
        'period_type' => 'INSTANT',
        'context_status' => 'RESOLVED',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => "RAW-{$id}",
        'filing_id' => $filingId,
        'source_concept' => 'Assets',
        'raw_value' => '100.25',
        'context_ref' => "CTX-{$id}",
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
    ]);

    return NormalizedFact::query()->create([
        'normalized_fact_id' => $id,
        'filing_id' => $filingId,
        'raw_fact_id' => "RAW-{$id}",
        'issuer_code' => 'HSSA',
        'period' => 'FY 2025',
        'period_end' => '2025-12-31',
        'canonical_concept' => 'quality_total_assets',
        'value' => '100.25',
        'currency' => 'IDR',
        'data_type' => 'MONETARY',
        'source_concept' => 'Assets',
        'mapping_rule_id' => 'QUALITY-MAPPING',
        'mapping_rule_version' => 1,
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'VERIFIED',
    ]);
}

function qualityValidation(string $id, string $filingId, string $dataset, string $ruleSet, string $ruleCode, int $version, string $result): ValidationResult
{
    return ValidationResult::query()->create([
        'validation_result_id' => $id,
        'filing_id' => $filingId,
        'normalized_dataset_version' => $dataset,
        'validation_rule_set_version' => $ruleSet,
        'normalized_fact_ids' => ['NF-QUALITY-001', 'NF-QUALITY-002'],
        'rule_code' => $ruleCode,
        'rule_version' => $version,
        'result' => $result,
        'severity' => $result === 'FAIL' ? 'ERROR' : 'INFO',
        'message' => 'Quality check result.',
        'checked_at' => now(),
    ]);
}
