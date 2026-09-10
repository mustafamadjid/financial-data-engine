<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\HissaCoreMockClient;

uses(RefreshDatabase::class);

it('accepts valid fixtures and rejects invalid fixtures through one consumer parser', function (): void {
    $client = new HissaCoreMockClient;
    $valid = $client->acceptJson(file_get_contents(dirname(__DIR__, 5).'/contracts/api/v1/fixtures/valid/published-filing.json'));

    expect($valid->snapshotId())->toBe('PUB-001');

    foreach (['non-verified.json', 'numeric-value.json', 'missing-lineage.json', 'invented-unmapped-fact.json'] as $fixture) {
        expect(fn () => $client->acceptJson(file_get_contents(dirname(__DIR__, 5).'/contracts/api/v1/fixtures/invalid/'.$fixture)))
            ->toThrow(InvalidArgumentException::class);
    }
});

it('accepts a live api response with the same consumer boundary', function (): void {
    config(['integration-api.enabled' => true, 'integration-api.token' => 'api-token']);
    $filing = phase11ApiFiling('FIL-COMPAT-1');
    phase11ApiSnapshot($filing, 'PUB-COMPAT-1', '2026-09-09 10:00:00');

    $response = $this->withToken('api-token')->get('/api/v1/snapshots/PUB-COMPAT-1');
    $accepted = (new HissaCoreMockClient)->acceptResponse($response->baseResponse);

    expect($accepted->snapshotId())->toBe('PUB-COMPAT-1')
        ->and($accepted->document['facts'][0]['value'])->toBeString()
        ->and($accepted->document['limitations']['items'])->toBeArray();
});
