<?php

use App\Domain\FinancialData\Publishing\Exceptions\InvalidPublishContractException;
use App\Domain\FinancialData\Publishing\PublishContractValidator;

it('accepts a complete verified publish payload', function () {
    (new PublishContractValidator)->validate(publishContractPayload());

    expect(true)->toBeTrue();
});

it('rejects a publish payload without required contract identity', function () {
    $payload = publishContractPayload();
    unset($payload['contract']);

    expect(fn () => (new PublishContractValidator)->validate($payload))
        ->toThrow(InvalidPublishContractException::class);
});

it('rejects non-verified quality', function () {
    $payload = publishContractPayload();
    $payload['quality']['status'] = 'REVIEW_REQUIRED';

    expect(fn () => (new PublishContractValidator)->validate($payload))
        ->toThrow(InvalidPublishContractException::class);
});

it('rejects missing raw fact lineage', function () {
    $payload = publishContractPayload();
    $payload['lineage']['raw_fact_ids'] = [];

    expect(fn () => (new PublishContractValidator)->validate($payload))
        ->toThrow(InvalidPublishContractException::class);
});

/** @return array<string, mixed> */
function publishContractPayload(): array
{
    return [
        'contract' => 'hissa.financial-data.publish',
        'contract_version' => '1.0.0',
        'filing' => [
            'filing_id' => 'FIL-PUBLISH-UNIT',
            'revision_number' => 1,
            'issuer_code' => 'TEST',
            'report_type' => 'ANNUAL',
            'fiscal_year' => 2025,
            'fiscal_period' => 'FY',
            'period_end' => '2025-12-31',
        ],
        'quality' => [
            'status' => 'VERIFIED',
            'validation_rule_set_version' => 'rules-1',
            'normalized_dataset_version' => 'dataset-1',
        ],
        'source' => [
            'source_url' => 'https://example.test/filing',
            'source_hash' => 'sha256:test',
            'storage_reference' => null,
        ],
        'normalized_facts' => [[
            'normalized_fact_id' => 'NF-PUBLISH-UNIT',
            'raw_fact_id' => 'RF-PUBLISH-UNIT',
            'canonical_concept' => 'total_assets',
            'value' => '100.000000000000000000',
            'currency' => 'IDR',
            'scope' => 'CONSOLIDATED',
            'period_end' => '2025-12-31',
            'period_start' => null,
            'data_type' => 'REPORTED',
            'mapping_rule_id' => 'MAP-PUBLISH-UNIT',
            'mapping_rule_version' => 1,
            'normalization_version' => '1.0.0@mapping-1',
            'validation_status' => 'VERIFIED',
        ]],
        'lineage' => [
            'raw_fact_ids' => ['RF-PUBLISH-UNIT'],
            'normalized_fact_ids' => ['NF-PUBLISH-UNIT'],
            'validation_result_ids' => ['VR-PUBLISH-UNIT'],
            'mapping_versions' => ['1.0.0@mapping-1'],
            'normalization_version' => '1.0.0@mapping-1',
            'normalized_dataset_version' => 'dataset-1',
        ],
        'limitations' => ['items' => [], 'unmapped_concepts' => []],
    ];
}
