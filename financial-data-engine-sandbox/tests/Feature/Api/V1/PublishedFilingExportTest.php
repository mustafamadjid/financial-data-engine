<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns byte-identical canonical detail and export documents', function (): void {
    config(['integration-api.enabled' => true, 'integration-api.token' => 'api-token']);
    $filing = phase11ApiFiling('FIL-EXPORT-1');
    phase11ApiSnapshot($filing, 'PUB-EXPORT-1', '2026-09-09 10:00:00');

    $detail = $this->withToken('api-token')->get('/api/v1/filings/'.$filing->filing_id);
    $export = $this->withToken('api-token')->get('/api/v1/filings/'.$filing->filing_id.'/export');

    expect($detail->status())->toBe(200)
        ->and($export->status())->toBe(200)
        ->and($detail->getContent())->toBe($export->getContent())
        ->and($export->headers->get('Content-Type'))->toContain('application/json')
        ->and($export->headers->get('Content-Disposition'))->toContain('TEST-FIL-EXPORT-1-r1-PUB-EXPORT-1-v0.1.0.json');
});
