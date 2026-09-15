<?php

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\RawFact;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('projects mapped and unmapped source series with full-dataset affected counts', function (): void {
    [$mappedSeries, $unmappedSeries] = conceptMappingFixture();

    $response = $this->getJson('/ops/data/concept-mappings?per_page=25');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.mappingSeriesKey', $mappedSeries)
        ->assertJsonPath('data.0.displayStatus', 'APPROVED')
        ->assertJsonPath('data.0.currentMapping.version', 2)
        ->assertJsonPath('data.0.affectedFilingCount', 2)
        ->assertJsonPath('data.1.mappingSeriesKey', $unmappedSeries)
        ->assertJsonPath('data.1.displayStatus', 'UNMAPPED')
        ->assertJsonPath('data.1.currentMapping', null)
        ->assertJsonPath('data.1.affectedFilingCount', 1);
});

it('returns append-only history, canonical options, and server impact preview', function (): void {
    [$mappedSeries] = conceptMappingFixture();

    $this->getJson("/ops/data/concept-mappings/{$mappedSeries}/history")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.1.version', 1)
        ->assertJsonPath('data.1.mappingRuleId', 'MAP-CM-001');

    $this->getJson('/ops/data/concept-mappings/canonical-options?search=asset')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'total_assets');

    $this->getJson("/ops/data/concept-mappings/{$mappedSeries}/impact?version=2")
        ->assertOk()
        ->assertJsonPath('data.mappingSetVersion', 2)
        ->assertJsonPath('data.affectedFilingCount', 2)
        ->assertJsonPath('data.selectedMapping.version', 2)
        ->assertJsonPath('data.filings.0.filingId', 'FIL-CM-001')
        ->assertJsonPath('data.upstream.automaticReprocess', false)
        ->assertJsonPath('data.allowedActions.reprocess.allowed', true);
});

/** @return array{0: string, 1: string} */
function conceptMappingFixture(): array
{
    $mappedSeries = MappingSeriesKey::from('Assets', 'general');
    $unmappedSeries = MappingSeriesKey::from('Liabilities', 'general');

    CanonicalConcept::query()->create([
        'code' => 'total_assets', 'name' => 'Total assets', 'statement' => 'BALANCE_SHEET', 'period_type' => 'INSTANT',
    ]);
    Filing::query()->create([
        'filing_id' => 'FIL-CM-001', 'issuer_code' => 'HSSA', 'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/cm-1', 'source_type' => 'XBRL_INSTANCE', 'source_hash' => str_repeat('c', 64), 'revision_number' => 1, 'taxonomy_entry_point' => 'general',
    ]);
    Filing::query()->create([
        'filing_id' => 'FIL-CM-002', 'issuer_code' => 'HSSB', 'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/cm-2', 'source_type' => 'XBRL_INSTANCE', 'source_hash' => str_repeat('d', 64), 'revision_number' => 2, 'taxonomy_entry_point' => 'general',
    ]);
    XbrlContext::query()->create([
        'context_id' => 'CTX-CM-001', 'filing_id' => 'FIL-CM-001', 'source_context_id' => 'ctx-1', 'entity_identifier' => 'HSSA', 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT', 'instant_date' => '2025-12-31', 'context_status' => 'RESOLVED',
    ]);
    XbrlContext::query()->create([
        'context_id' => 'CTX-CM-002', 'filing_id' => 'FIL-CM-002', 'source_context_id' => 'ctx-2', 'entity_identifier' => 'HSSB', 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT', 'instant_date' => '2025-12-31', 'context_status' => 'RESOLVED',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => 'RAW-CM-001', 'filing_id' => 'FIL-CM-001', 'source_concept' => 'Assets', 'raw_value' => '1', 'context_ref' => 'CTX-CM-001', 'is_nil' => false, 'fact_status' => 'EXTRACTED',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => 'RAW-CM-002', 'filing_id' => 'FIL-CM-002', 'source_concept' => 'Assets', 'raw_value' => '2', 'context_ref' => 'CTX-CM-002', 'is_nil' => false, 'fact_status' => 'EXTRACTED',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => 'RAW-CM-003', 'filing_id' => 'FIL-CM-001', 'source_concept' => 'Liabilities', 'raw_value' => '3', 'context_ref' => 'CTX-CM-001', 'is_nil' => false, 'fact_status' => 'EXTRACTED',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-CM-001', 'mapping_series_key' => $mappedSeries, 'source_concept' => 'Assets', 'entry_point' => 'general', 'canonical_concept' => 'total_assets', 'status' => 'APPROVED', 'rule_version' => 1, 'rationale' => 'Initial',
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-CM-002', 'mapping_series_key' => $mappedSeries, 'source_concept' => 'Assets', 'entry_point' => 'general', 'canonical_concept' => 'total_assets', 'status' => 'APPROVED', 'rule_version' => 2, 'rationale' => 'Revised', 'supersedes_mapping_rule_id' => 'MAP-CM-001',
    ]);

    return [$mappedSeries, $unmappedSeries];
}
