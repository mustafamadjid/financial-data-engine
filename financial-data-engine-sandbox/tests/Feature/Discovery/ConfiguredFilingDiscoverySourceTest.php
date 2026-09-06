<?php

use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Infrastructure\Discovery\ConfiguredFilingDiscoverySource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('loads valid candidates from the configured sandbox fixture and applies criteria', function () {
    Storage::fake('local');
    config([
        'financial-pipeline.discovery.disk' => 'local',
        'financial-pipeline.discovery.fixture_path' => 'financial-pipeline/discovery/candidates.json',
    ]);
    Storage::disk('local')->put('financial-pipeline/discovery/candidates.json', json_encode([
        discoveryCandidatePayload('FIL-001', 'ANTM'),
        discoveryCandidatePayload('FIL-002', 'BBCA'),
    ], JSON_THROW_ON_ERROR));

    $candidates = iterator_to_array((new ConfiguredFilingDiscoverySource)->discover(
        new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', issuerCode: 'ANTM', pageSize: 10),
    ));

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->filingId)->toBe('FIL-001')
        ->and($candidates[0]->issuerCode)->toBe('ANTM');
});

it('rejects an invalid candidate without preventing valid candidates from being discovered', function () {
    Storage::fake('local');
    Log::spy();
    config([
        'financial-pipeline.discovery.disk' => 'local',
        'financial-pipeline.discovery.fixture_path' => 'financial-pipeline/discovery/candidates.json',
    ]);
    Storage::disk('local')->put('financial-pipeline/discovery/candidates.json', json_encode([
        ['filing_id' => '../invalid'],
        discoveryCandidatePayload('FIL-VALID', 'ANTM'),
    ], JSON_THROW_ON_ERROR));

    $candidates = iterator_to_array((new ConfiguredFilingDiscoverySource)->discover(
        new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 10),
    ));

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->filingId)->toBe('FIL-VALID');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'pipeline.discovery.candidate_rejected'
            && $context['source_adapter'] === 'configured');
});

it('rejects fixture paths outside the configured storage root', function () {
    config([
        'financial-pipeline.discovery.disk' => 'local',
        'financial-pipeline.discovery.fixture_path' => '../secrets/candidates.json',
    ]);

    expect(fn () => iterator_to_array((new ConfiguredFilingDiscoverySource)->discover(
        new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 10),
    )))->toThrow(InvalidArgumentException::class);
});

function discoveryCandidatePayload(string $filingId, string $issuerCode, int $revisionNumber = 1, ?string $supersedesFilingId = null): array
{
    return [
        'filing_id' => $filingId,
        'issuer_code' => $issuerCode,
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_start' => '2026-01-01',
        'period_end' => '2026-06-30',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => "sha256:{$filingId}",
        'revision_number' => $revisionNumber,
        'supersedes_filing_id' => $supersedesFilingId,
        'discovered_at' => '2026-09-06T10:00:00+07:00',
    ];
}
