<?php

use App\Models\PublishedSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes an approved published sample through detail and export', function (): void {
    $filing = phase11ApiFiling('FIL-E2E-API-1');
    phase11ApiSnapshot($filing, 'PUB-E2E-API-1', '2026-09-09 10:00:00');

    $detail = $this->getJson('/api/v1/filings/'.$filing->filing_id);
    $export = $this->get('/api/v1/filings/'.$filing->filing_id.'/export');

    $detail->assertOk()->assertJsonPath('quality.status', 'VERIFIED');
    expect($detail->json('snapshot.snapshot_id'))->toBe('PUB-E2E-API-1')
        ->and($detail->getContent())->toBe($export->getContent());
});

it('isolates an invalid or unpublished filing from every read endpoint', function (): void {
    phase11ApiFiling('FIL-E2E-API-INVALID');

    $this->getJson('/api/v1/filings/FIL-E2E-API-INVALID')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'PUBLISHED_FILING_NOT_FOUND');
    $this->getJson('/api/v1/issuers/TEST/filings')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('keeps historical snapshots stable after a newer reprocess snapshot', function (): void {
    $filing = phase11ApiFiling('FIL-E2E-API-HISTORY');
    phase11ApiSnapshot($filing, 'PUB-E2E-API-A', '2026-09-09 10:00:00');
    phase11ApiSnapshot($filing, 'PUB-E2E-API-B', '2026-09-09 11:00:00');

    $latest = $this->getJson('/api/v1/filings/'.$filing->filing_id);
    $historical = $this->get('/api/v1/snapshots/PUB-E2E-API-A');
    $historicalAgain = $this->get('/api/v1/snapshots/PUB-E2E-API-A');

    expect($latest->json('snapshot.snapshot_id'))->toBe('PUB-E2E-API-B')
        ->and($historical->getContent())->toBe($historicalAgain->getContent())
        ->and($historical->headers->get('ETag'))->toBe($historicalAgain->headers->get('ETag'));
});

it('keeps filing revisions addressable and orders the issuer list deterministically', function (): void {
    $first = phase11ApiFiling('FIL-E2E-API-R1');
    phase11ApiSnapshot($first, 'PUB-E2E-API-R1', '2026-09-09 10:00:00');
    $second = phase11ApiFiling('FIL-E2E-API-R2');
    $second->update(['revision_number' => 2, 'supersedes_filing_id' => $first->filing_id]);
    $payload = phase11ApiSnapshot($second, 'PUB-E2E-API-R2', '2026-09-09 12:00:00')->payload;
    $payload['filing']['revision_number'] = 2;
    $payload['filing']['supersedes_filing_id'] = $first->filing_id;
    PublishedSnapshot::query()->whereKey('PUB-E2E-API-R2')->update(['payload' => $payload, 'revision_number' => 2]);

    $list = $this->getJson('/api/v1/issuers/TEST/filings');

    $list->assertOk();
    expect($this->getJson('/api/v1/filings/'.$first->filing_id)->json('filing.filing_id'))->toBe($first->filing_id)
        ->and($this->getJson('/api/v1/filings/'.$second->filing_id)->json('filing.supersedes_filing_id'))->toBe($first->filing_id)
        ->and($list->json('data.0.filing_id'))->toBe($second->filing_id);
});
