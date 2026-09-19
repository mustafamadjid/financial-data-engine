<?php

use App\Domain\FinancialData\Validation\DaValidationRule;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RawFact;
use App\Models\RestatementEvent;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('persists an idempotent restatement event without mutating either fact', function () {
    CanonicalConcept::query()->create(['code' => 'profit_loss', 'name' => 'Profit/Loss', 'statement' => 'INCOME_STATEMENT', 'period_type' => 'DURATION', 'sign_convention' => 'AS_REPORTED', 'allowed_scope' => ['CONSOLIDATED']]);
    $filingDefaults = ['issuer_code' => 'TLKM', 'source_url' => 'https://example.test/filing.xbrl', 'source_type' => 'XBRL_INSTANCE', 'source_hash' => 'hash', 'revision_number' => 1, 'processing_stage' => 'VALIDATING', 'quality_status' => 'PENDING'];
    $prior = Filing::query()->create($filingDefaults + ['filing_id' => 'FIL-CRX-PRIOR', 'fiscal_year' => 2025, 'fiscal_period' => 'Q2', 'period_end' => '2025-06-30']);
    $current = Filing::query()->create($filingDefaults + ['filing_id' => 'FIL-CRX-CURRENT', 'fiscal_year' => 2026, 'fiscal_period' => 'Q2', 'period_end' => '2026-06-30', 'supersedes_filing_id' => $prior->filing_id]);
    $mapping = ConceptMapping::query()->create(['mapping_rule_id' => 'MAP-CRX', 'source_concept' => 'ProfitLoss', 'canonical_concept' => 'profit_loss', 'status' => 'APPROVED', 'rule_version' => 1]);
    foreach ([$prior, $current] as $filing) {
        XbrlContext::query()->create(['context_id' => 'CTX-'.$filing->filing_id, 'filing_id' => $filing->filing_id, 'source_context_id' => 'c1', 'entity_identifier' => 'TLKM', 'period_type' => 'DURATION', 'context_status' => 'RESOLVED']);
        RawFact::query()->create(['raw_fact_id' => 'RF-'.$filing->filing_id, 'filing_id' => $filing->filing_id, 'source_concept' => 'ProfitLoss', 'raw_value' => '100', 'context_ref' => 'CTX-'.$filing->filing_id, 'is_nil' => false, 'fact_status' => 'EXTRACTED']);
    }
    NormalizedFact::query()->create(['normalized_fact_id' => 'NF-CRX-PRIOR', 'filing_id' => $prior->filing_id, 'raw_fact_id' => 'RF-'.$prior->filing_id, 'issuer_code' => 'TLKM', 'canonical_concept' => 'profit_loss', 'value' => '100', 'period_end' => '2025-06-30', 'data_type' => 'numeric', 'source_concept' => 'ProfitLoss', 'mapping_rule_id' => $mapping->mapping_rule_id, 'mapping_rule_version' => 1, 'normalization_status' => 'NORMALIZED', 'validation_status' => 'PENDING']);
    $currentFact = NormalizedFact::query()->create(['normalized_fact_id' => 'NF-CRX-CURRENT', 'filing_id' => $current->filing_id, 'raw_fact_id' => 'RF-'.$current->filing_id, 'issuer_code' => 'TLKM', 'canonical_concept' => 'profit_loss', 'value' => '90', 'period_end' => '2026-06-30', 'data_type' => 'numeric', 'source_concept' => 'ProfitLoss', 'mapping_rule_id' => $mapping->mapping_rule_id, 'mapping_rule_version' => 1, 'normalization_status' => 'NORMALIZED', 'validation_status' => 'PENDING']);
    $currentFact->setRelation('canonicalConcept', CanonicalConcept::query()->first());

    $context = new FilingValidationContext($current, [$currentFact]);
    $rule = new DaValidationRule('CRX-001');
    $first = $rule->evaluate($context);
    $second = $rule->evaluate($context);

    expect($first->result)->toBe('REVIEW_REQUIRED')
        ->and($second->result)->toBe('REVIEW_REQUIRED')
        ->and(RestatementEvent::query()->count())->toBe(1)
        ->and(NormalizedFact::query()->find('NF-CRX-PRIOR'))->not->toBeNull();
});
