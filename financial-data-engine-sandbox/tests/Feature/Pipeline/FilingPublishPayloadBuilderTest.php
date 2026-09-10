<?php

use App\Domain\FinancialData\Publishing\FilingPublishPayloadBuilder;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a deterministic publish payload with complete lineage', function () {
    $filing = Filing::query()->create([
        'filing_id' => 'FIL-PAYLOAD-1',
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/FIL-PAYLOAD-1.xbrl',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', 'payload'),
        'revision_number' => 1,
        'processing_stage' => 'PUBLISHING',
        'quality_status' => 'VERIFIED',
    ]);
    CanonicalConcept::query()->create([
        'code' => 'total_assets',
        'name' => 'Total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-PAYLOAD-1',
        'source_concept' => 'Assets',
        'canonical_concept' => 'total_assets',
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-PAYLOAD-1',
        'filing_id' => $filing->filing_id,
        'source_context_id' => 'c1',
        'entity_identifier' => 'TEST',
        'scope' => 'CONSOLIDATED',
        'period_type' => 'INSTANT',
        'instant_date' => '2025-12-31',
        'context_status' => 'RESOLVED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $raw = RawFact::query()->create([
        'raw_fact_id' => 'RAW-PAYLOAD-1',
        'filing_id' => $filing->filing_id,
        'source_concept' => 'Assets',
        'raw_value' => '100',
        'normalized_numeric_value' => '100',
        'context_ref' => $context->context_id,
        'unit_ref' => null,
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $normalized = NormalizedFact::query()->create([
        'normalized_fact_id' => 'NF-PAYLOAD-1',
        'filing_id' => $filing->filing_id,
        'raw_fact_id' => $raw->raw_fact_id,
        'issuer_code' => 'TEST',
        'period' => 'FY',
        'period_end' => '2025-12-31',
        'canonical_concept' => 'total_assets',
        'value' => '100',
        'currency' => 'IDR',
        'scope' => 'CONSOLIDATED',
        'data_type' => 'REPORTED',
        'source_concept' => 'Assets',
        'mapping_rule_id' => 'MAP-PAYLOAD-1',
        'mapping_rule_version' => 1,
        'normalization_version' => '1.0.0@mapping-1',
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'PENDING',
    ]);
    $validation = ValidationResult::query()->create([
        'validation_result_id' => 'VR-PAYLOAD-1',
        'filing_id' => $filing->filing_id,
        'normalized_dataset_version' => 'dataset-1',
        'validation_rule_set_version' => 'rules-1',
        'normalized_fact_ids' => [$normalized->normalized_fact_id],
        'rule_code' => 'ASSET-001',
        'rule_version' => 1,
        'result' => 'PASS',
        'severity' => 'INFO',
        'message' => 'Passed.',
        'checked_at' => now(),
    ]);

    $payload = app(FilingPublishPayloadBuilder::class)->build($filing);
    $filing->update(['source_url' => 'https://example.test/changed']);
    $normalized->update(['value' => '99.99']);

    expect($payload['contract'])->toBe('hissa.financial-data.publish')
        ->and($payload['filing']['filing_id'])->toBe($filing->filing_id)
        ->and($payload['filing'])->toHaveKeys(['period_start', 'supersedes_filing_id'])
        ->and($payload)->toHaveKeys(['source', 'limitations'])
        ->and($payload['source']['source_url'])->toBe('https://example.test/FIL-PAYLOAD-1.xbrl')
        ->and($payload['source']['source_hash'])->toBe(hash('sha256', 'payload'))
        ->and($payload['normalized_facts'][0]['value'])->toBe('100.000000000000000000')
        ->and($payload['normalized_facts'][0])->toHaveKeys(['period_start', 'data_type', 'validation_status'])
        ->and($payload['lineage']['raw_fact_ids'])->toBe([$raw->raw_fact_id])
        ->and($payload['lineage']['normalized_fact_ids'])->toBe([$normalized->normalized_fact_id])
        ->and($payload['lineage']['validation_result_ids'])->toBe([$validation->validation_result_id])
        ->and($payload['quality']['validation_rule_set_version'])->toBe('rules-1')
        ->and($payload['source']['source_url'])->toBe('https://example.test/FIL-PAYLOAD-1.xbrl')
        ->and($payload['normalized_facts'][0]['value'])->toBe('100.000000000000000000');
});
