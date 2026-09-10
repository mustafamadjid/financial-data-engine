<?php

it('validates the v1 contract artifacts and valid fixtures', function (string $fixtureName): void {
    $fixture = apiV1Fixture("valid/{$fixtureName}");

    expect($fixture)
        ->toHaveKeys(['api_version', 'contract', 'contract_version', 'snapshot', 'filing', 'quality', 'facts', 'provenance', 'limitations'])
        ->and($fixture['api_version'])->toBe('v1')
        ->and($fixture['contract'])->toBe('hissa.financial-data.integration')
        ->and($fixture['contract_version'])->toBe('0.1.0')
        ->and($fixture['quality']['status'])->toBe('VERIFIED')
        ->and($fixture['limitations'])->toHaveKeys(['items', 'unmapped_concepts']);

    foreach ($fixture['facts'] as $fact) {
        expect($fact)->toHaveKeys([
            'normalized_fact_id', 'raw_fact_id', 'canonical_concept', 'value', 'currency', 'scope',
            'period_start', 'period_end', 'data_type', 'mapping_rule_id', 'mapping_rule_version',
            'normalization_version', 'validation_status',
        ])
            ->and($fact['value'])->toBeString();
    }
})->with([
    'published-filing.json',
    'published-filing-with-limitations.json',
]);

it('validates the v1 list fixture envelope', function (): void {
    $fixture = apiV1Fixture('valid/published-filing-list.json');

    expect($fixture)
        ->toHaveKeys(['api_version', 'contract', 'contract_version', 'data', 'pagination'])
        ->and($fixture['contract'])->toBe('hissa.financial-data.integration-list')
        ->and($fixture['pagination'])->toHaveKeys(['limit', 'next_cursor', 'has_more']);
});

it('rejects each negative fixture for its named contract violation', function (string $fixtureName, callable $assertion): void {
    $fixture = apiV1Fixture("invalid/{$fixtureName}");

    $assertion($fixture);
})->with([
    ['non-verified.json', fn (array $fixture): bool => $fixture['quality']['status'] !== 'VERIFIED'],
    ['numeric-value.json', fn (array $fixture): bool => ! is_string($fixture['facts'][0]['value'])],
    ['missing-lineage.json', fn (array $fixture): bool => ! isset($fixture['provenance']['normalized_fact_ids'])],
    ['invented-unmapped-fact.json', fn (array $fixture): bool => $fixture['facts'][0]['canonical_concept'] === 'UNMAPPED'],
]);

it('contains the machine-readable schemas and exactly four api paths', function (): void {
    foreach (['openapi.yaml', 'published-filing.schema.json', 'published-filing-list.schema.json', 'error.schema.json', 'README.md'] as $artifact) {
        expect(apiV1Path($artifact))->toBeFile();
    }

    $openApi = file_get_contents(apiV1Path('openapi.yaml'));

    expect($openApi)
        ->toContain('/api/v1/filings/{filing_id}:')
        ->toContain('/api/v1/snapshots/{snapshot_id}:')
        ->toContain('/api/v1/issuers/{issuer_code}/filings:')
        ->toContain('/api/v1/filings/{filing_id}/export:');
});

function apiV1Path(string $path = ''): string
{
    $repositoryRoot = dirname(__DIR__, 3);

    return $repositoryRoot.'/contracts/api/v1'.($path !== '' ? '/'.$path : '');
}

/** @return array<string, mixed> */
function apiV1Fixture(string $path): array
{
    $decoded = json_decode(file_get_contents(apiV1Path('fixtures/'.$path)), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();

    return $decoded;
}
