<?php

use App\Domain\FinancialData\Download\Exceptions\TerminalDownloadException;
use App\Infrastructure\Storage\FilingArtifactStorage;
use App\Models\Filing;
use App\Models\FilingArtifact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores an artifact atomically with a sha256 hash and stable path', function () {
    Storage::fake('local');
    $filing = makeStorageFiling('FIL-STORE-1');
    $storage = app(FilingArtifactStorage::class);

    $temporaryPath = $storage->storeTemporary($filing->filing_id, 'filing.zip', 'xbrl-content');
    $artifact = $storage->persist($filing, $temporaryPath, 'XBRL_INSTANCE', 'filing.zip', 'application/zip');

    expect($artifact)->toBeInstanceOf(FilingArtifact::class)
        ->and($artifact->source_hash)->toBe(hash('sha256', 'xbrl-content'))
        ->and($artifact->storage_path)->toContain('ANTM/2026-06-30/1/')
        ->and(Storage::disk('local')->exists($artifact->storage_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($temporaryPath))->toBeFalse()
        ->and(FilingArtifact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1);
});

it('deduplicates the same filing artifact hash and keeps the original stable path', function () {
    Storage::fake('local');
    $filing = makeStorageFiling('FIL-STORE-2');
    $storage = app(FilingArtifactStorage::class);

    $first = $storage->persist($filing, $storage->storeTemporary($filing->filing_id, 'filing.zip', 'same'), 'XBRL_INSTANCE', 'filing.zip', 'application/zip');
    $second = $storage->persist($filing, $storage->storeTemporary($filing->filing_id, 'filing.zip', 'same'), 'XBRL_INSTANCE', 'filing.zip', 'application/zip');

    expect($second->artifact_id)->toBe($first->artifact_id)
        ->and($second->storage_path)->toBe($first->storage_path)
        ->and(FilingArtifact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1);
});

it('keeps artifacts for different filing revisions separate', function () {
    Storage::fake('local');
    $firstFiling = makeStorageFiling('FIL-STORE-R1', revision: 1);
    $secondFiling = makeStorageFiling('FIL-STORE-R2', revision: 2);
    $storage = app(FilingArtifactStorage::class);

    $first = $storage->persist($firstFiling, $storage->storeTemporary($firstFiling->filing_id, 'filing.zip', 'same'), 'XBRL_INSTANCE', 'filing.zip', 'application/zip');
    $second = $storage->persist($secondFiling, $storage->storeTemporary($secondFiling->filing_id, 'filing.zip', 'same'), 'XBRL_INSTANCE', 'filing.zip', 'application/zip');

    expect($first->artifact_id)->not->toBe($second->artifact_id)
        ->and($first->storage_path)->not->toBe($second->storage_path)
        ->and(FilingArtifact::query()->count())->toBe(2);
});

it('rejects an empty artifact before it is persisted', function () {
    Storage::fake('local');
    $filing = makeStorageFiling('FIL-STORE-EMPTY');
    $storage = app(FilingArtifactStorage::class);

    $exception = null;

    try {
        $storage->persist(
            $filing,
            $storage->storeTemporary($filing->filing_id, 'filing.zip', ''),
            'XBRL_INSTANCE',
            'filing.zip',
            'application/zip',
        );
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(TerminalDownloadException::class);
});

function makeStorageFiling(string $filingId, int $revision = 1): Filing
{
    return Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'ANTM',
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_end' => '2026-06-30',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => "sha256:discovered-{$filingId}",
        'revision_number' => $revision,
        'discovered_at' => now(),
        'processing_stage' => 'DOWNLOADING',
        'quality_status' => 'PENDING',
    ]);
}
