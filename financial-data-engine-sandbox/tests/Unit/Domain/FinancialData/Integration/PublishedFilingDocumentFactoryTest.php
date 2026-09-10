<?php

use App\Domain\FinancialData\Integration\Exceptions\IntegrationContractViolation;
use App\Domain\FinancialData\Integration\PublishedFilingDocumentFactory;
use App\Models\PublishedSnapshot;
use Tests\TestCase;

uses(TestCase::class);

it('maps an internal published snapshot to the external document contract', function (): void {
    $snapshot = new PublishedSnapshot([
        'snapshot_id' => 'PUB-FACTORY-1',
        'published_at' => '2026-09-09 03:30:00',
        'payload' => integrationInternalPayload(),
        'lineage' => ['pipeline_run_id' => 42],
    ]);

    $document = (new PublishedFilingDocumentFactory)->make($snapshot)->toArray();

    expect(array_keys($document))->toBe([
        'api_version', 'contract', 'contract_version', 'snapshot', 'filing', 'quality',
        'facts', 'provenance', 'limitations',
    ])
        ->and($document['quality']['status'])->toBe('VERIFIED')
        ->and($document['provenance']['pipeline_run_id'])->toBe(42)
        ->and($document['limitations'])->toBe(['items' => [], 'unmapped_concepts' => []]);
});

it('sorts facts and lineage in canonical order and returns a stable etag', function (): void {
    $payload = integrationInternalPayload();
    $payload['normalized_facts'] = array_reverse($payload['normalized_facts']);
    $payload['lineage']['raw_fact_ids'] = ['RAW-2', 'RAW-1', 'RAW-1'];

    $snapshot = new PublishedSnapshot([
        'snapshot_id' => 'PUB-FACTORY-2',
        'published_at' => '2026-09-09 03:30:00',
        'payload' => $payload,
        'lineage' => ['pipeline_run_id' => 43],
    ]);

    $document = (new PublishedFilingDocumentFactory)->make($snapshot);

    expect($document->toArray()['facts'][0]['normalized_fact_id'])->toBe('NF-1')
        ->and($document->toArray()['provenance']['raw_fact_ids'])->toBe(['RAW-1', 'RAW-2'])
        ->and($document->canonicalJson())->toBe(json_encode($document->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        ->and($document->etag())->toBe('sha256-'.hash('sha256', $document->canonicalJson()));
});

it('rejects unsafe or incomplete snapshots with safe violation codes', function (string $code): void {
    $payload = integrationInternalPayload();
    if ($code === 'NON_VERIFIED_SNAPSHOT') {
        $payload['quality']['status'] = 'REVIEW_REQUIRED';
    } elseif ($code === 'UNSAFE_STORAGE_REFERENCE') {
        $payload['source']['storage_reference'] = 'C:\\secrets\\filing.zip';
    } elseif ($code === 'INVALID_DECIMAL_STRING') {
        $payload['normalized_facts'][0]['value'] = 100;
    } else {
        unset($payload['lineage']['raw_fact_ids']);
    }
    $snapshot = new PublishedSnapshot([
        'snapshot_id' => 'PUB-FACTORY-BAD',
        'published_at' => '2026-09-09 03:30:00',
        'payload' => $payload,
        'lineage' => ['pipeline_run_id' => 44],
    ]);

    expect(fn () => (new PublishedFilingDocumentFactory)->make($snapshot))
        ->toThrow(fn (IntegrationContractViolation $exception): bool => $exception->violationCode() === $code);
})->with([
    'NON_VERIFIED_SNAPSHOT',
    'UNSAFE_STORAGE_REFERENCE',
    'INVALID_DECIMAL_STRING',
    'MISSING_FACT_LINEAGE',
]);

it('rejects malformed limitation items with a dedicated violation code', function (): void {
    $payload = integrationInternalPayload();
    $payload['limitations']['items'] = [['code' => 'BROKEN']];
    $snapshot = new PublishedSnapshot([
        'snapshot_id' => 'PUB-FACTORY-LIMITATION',
        'published_at' => '2026-09-09 03:30:00',
        'payload' => $payload,
        'lineage' => ['pipeline_run_id' => 46],
    ]);

    expect(fn () => (new PublishedFilingDocumentFactory)->make($snapshot))
        ->toThrow(fn (IntegrationContractViolation $exception): bool => $exception->violationCode() === 'INVALID_LIMITATION');
});

/** @return array<string, mixed> */
function integrationInternalPayload(): array
{
    return [
        'contract' => 'hissa.financial-data.publish',
        'contract_version' => '1.0.0',
        'filing' => [
            'filing_id' => 'FIL-FACTORY-1',
            'issuer_code' => 'TEST',
            'report_type' => 'ANNUAL',
            'fiscal_year' => 2025,
            'fiscal_period' => 'FY',
            'period_start' => null,
            'period_end' => '2025-12-31',
            'revision_number' => 1,
            'supersedes_filing_id' => null,
        ],
        'quality' => [
            'status' => 'VERIFIED',
            'validation_rule_set_version' => 'rules-1',
            'normalized_dataset_version' => 'dataset-1',
            'validation_result_ids' => ['VR-2', 'VR-1'],
        ],
        'source' => [
            'source_url' => 'https://example.test/filing',
            'source_type' => 'XBRL_INSTANCE',
            'source_hash' => 'sha256:test',
            'storage_reference' => 'filings/TEST/FIL-FACTORY-1.xbrl',
        ],
        'normalized_facts' => [
            [
                'normalized_fact_id' => 'NF-2', 'raw_fact_id' => 'RAW-2', 'canonical_concept' => 'total_liabilities',
                'value' => '200', 'currency' => 'IDR', 'scope' => 'CONSOLIDATED', 'period_start' => null,
                'period_end' => '2025-12-31', 'data_type' => 'REPORTED', 'mapping_rule_id' => 'MAP-2',
                'mapping_rule_version' => 1, 'normalization_version' => '1.0.0@mapping-1', 'validation_status' => 'VERIFIED',
            ],
            [
                'normalized_fact_id' => 'NF-1', 'raw_fact_id' => 'RAW-1', 'canonical_concept' => 'cash',
                'value' => '100', 'currency' => 'IDR', 'scope' => 'CONSOLIDATED', 'period_start' => null,
                'period_end' => '2025-12-31', 'data_type' => 'REPORTED', 'mapping_rule_id' => 'MAP-1',
                'mapping_rule_version' => 1, 'normalization_version' => '1.0.0@mapping-1', 'validation_status' => 'VERIFIED',
            ],
        ],
        'lineage' => [
            'raw_fact_ids' => ['RAW-2', 'RAW-1'],
            'normalized_fact_ids' => ['NF-2', 'NF-1'],
            'validation_result_ids' => ['VR-2', 'VR-1'],
            'mapping_versions' => ['1.0.0@mapping-1'],
            'normalization_version' => '1.0.0@mapping-1',
            'normalized_dataset_version' => 'dataset-1',
        ],
        'limitations' => ['items' => [], 'unmapped_concepts' => []],
    ];
}
