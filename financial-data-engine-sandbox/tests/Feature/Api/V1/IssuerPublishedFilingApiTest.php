<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns summary-only latest snapshots for an issuer with filters', function (): void {
    config(['integration-api.enabled' => true, 'integration-api.token' => 'api-token']);
    $filing = apiFiling('FIL-API-LIST-1');
    apiSnapshot($filing, 'PUB-API-LIST-1', '2026-09-09 10:00:00');

    $response = $this->withToken('api-token')->getJson('/api/v1/issuers/TEST/filings?report_type=ANNUAL&limit=25');

    $response->assertOk()
        ->assertJsonPath('data.0.filing_id', $filing->filing_id)
        ->assertJsonPath('data.0.quality_status', 'VERIFIED')
        ->assertJsonMissingPath('data.0.facts')
        ->assertJsonStructure(['api_version', 'contract', 'contract_version', 'data', 'pagination']);
});

it('returns the requested page size in list pagination metadata', function (): void {
    config(['integration-api.enabled' => true, 'integration-api.token' => 'api-token']);
    $filing = apiFiling('FIL-API-LIST-LIMIT');
    apiSnapshot($filing, 'PUB-API-LIST-LIMIT', '2026-09-09 10:00:00');

    $this->withToken('api-token')->getJson('/api/v1/issuers/TEST/filings?limit=100')
        ->assertOk()
        ->assertJsonPath('pagination.limit', 100);
});
