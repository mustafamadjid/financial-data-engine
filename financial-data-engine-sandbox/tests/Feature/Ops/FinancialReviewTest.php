<?php

use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RawFact;
use App\Models\XbrlContext;
use App\Models\XbrlUnit;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('serves a public paginated financial fact projection with decimal strings', function (): void {
    $fact = financialReviewFixture();

    $this->getJson('/ops/data/financial-facts?per_page=25')
        ->assertOk()
        ->assertJsonPath('data.0.normalizedFactId', $fact->normalized_fact_id)
        ->assertJsonPath('data.0.filing.filingId', 'FIL-FIN-001')
        ->assertJsonPath('data.0.canonicalConcept.code', 'total_assets')
        ->assertJsonPath('data.0.value', '1250000.500000000000000000')
        ->assertJsonPath('data.0.rawFact.context.contextId', 'CTX-FIN-001')
        ->assertJsonPath('data.0.mapping.ruleVersion', 2)
        ->assertJsonPath('meta.perPage', 25);
});

it('filters financial facts on the server and returns a detailed lineage projection', function (): void {
    $fact = financialReviewFixture();

    $this->getJson('/ops/data/financial-facts?search=Assets&validation_status=VERIFIED')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/ops/data/financial-facts/{$fact->normalized_fact_id}")
        ->assertOk()
        ->assertJsonPath('data.filing.filingId', 'FIL-FIN-001')
        ->assertJsonPath('data.rawFact.rawFactId', 'RAW-FIN-001')
        ->assertJsonPath('data.rawFact.unit.currency', 'IDR')
        ->assertJsonPath('data.mapping.entryPoint', 'general')
        ->assertJsonPath('data.allowedActions.markForReview.allowed', true);
});

function financialReviewFixture(): NormalizedFact
{
    Filing::query()->create([
        'filing_id' => 'FIL-FIN-001', 'issuer_code' => 'HSSA', 'report_type' => 'ANNUAL',
        'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/financial', 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => str_repeat('a', 64), 'revision_number' => 1,
        'taxonomy_entry_point' => 'general', 'processing_stage' => 'PUBLISHED', 'quality_status' => 'VERIFIED',
    ]);
    CanonicalConcept::query()->create([
        'code' => 'total_assets', 'name' => 'Total assets', 'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT', 'sign_convention' => 'AS_REPORTED',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-FIN-001', 'mapping_series_key' => str_repeat('b', 64),
        'source_concept' => 'Assets', 'entry_point' => 'general', 'canonical_concept' => 'total_assets',
        'status' => 'APPROVED', 'rule_version' => 2, 'created_by' => 'system',
    ]);
    XbrlContext::query()->create([
        'context_id' => 'CTX-FIN-001', 'filing_id' => 'FIL-FIN-001', 'source_context_id' => 'ctx-1',
        'entity_identifier' => 'HSSA', 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT',
        'instant_date' => '2025-12-31', 'context_status' => 'RESOLVED',
    ]);
    XbrlUnit::query()->create([
        'unit_id' => 'UNIT-FIN-001', 'filing_id' => 'FIL-FIN-001', 'source_unit_id' => 'IDR',
        'unit_type' => 'CURRENCY', 'measure' => 'iso4217:IDR', 'currency' => 'IDR',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => 'RAW-FIN-001', 'filing_id' => 'FIL-FIN-001', 'source_concept' => 'Assets',
        'raw_value' => '1250000.5', 'normalized_numeric_value' => '1250000.5', 'context_ref' => 'CTX-FIN-001',
        'unit_ref' => 'UNIT-FIN-001', 'is_nil' => false, 'fact_status' => 'EXTRACTED',
    ]);

    return NormalizedFact::query()->create([
        'normalized_fact_id' => 'NF-FIN-001', 'filing_id' => 'FIL-FIN-001', 'raw_fact_id' => 'RAW-FIN-001',
        'issuer_code' => 'HSSA', 'period' => 'FY 2025', 'period_end' => '2025-12-31',
        'canonical_concept' => 'total_assets', 'value' => '1250000.5', 'currency' => 'IDR',
        'scope' => 'CONSOLIDATED', 'data_type' => 'MONETARY', 'source_concept' => 'Assets',
        'mapping_rule_id' => 'MAP-FIN-001', 'mapping_rule_version' => 2, 'normalization_version' => '1.0.0@mapping-1',
        'normalization_status' => 'NORMALIZED', 'validation_status' => 'VERIFIED',
    ]);
}
