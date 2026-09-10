<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('supports the same strong etag and conditional response for detail snapshot and export', function (): void {
    config(['integration-api.enabled' => true, 'integration-api.token' => 'api-token']);
    $filing = phase11ApiFiling('FIL-ETAG-1');
    phase11ApiSnapshot($filing, 'PUB-ETAG-1', '2026-09-09 10:00:00');

    $detail = $this->withToken('api-token')->get('/api/v1/filings/'.$filing->filing_id);
    $etag = $detail->headers->get('ETag');
    $snapshot = $this->withToken('api-token')->get('/api/v1/snapshots/PUB-ETAG-1');
    $export = $this->withToken('api-token')->get('/api/v1/filings/'.$filing->filing_id.'/export');
    $notModified = $this->withToken('api-token')->withHeaders(['If-None-Match' => $etag])->get('/api/v1/filings/'.$filing->filing_id);

    expect($etag)->toStartWith('sha256-')
        ->and($snapshot->headers->get('ETag'))->toBe($etag)
        ->and($export->headers->get('ETag'))->toBe($etag)
        ->and($notModified->status())->toBe(304)
        ->and($notModified->getContent())->toBe('');
});
