<?php

use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DiscoverFilingsJob;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\PipelineJobRun;
use App\Models\RawFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('runs discovery download parse twice without duplicate raw data or source overwrite', function () {
    $source = file_get_contents(base_path('../python/xbrl-worker/tests/fixtures/minimal-valid/minimal-instance.xbrl'));
    expect($source)->toBeString()->not->toBeEmpty();
    $filingId = 'FIL-CHECKPOINT-1';
    Storage::fake('local');
    checkpointConfigureDiscovery($filingId);
    Http::fake(['https://example.test/*' => Http::response($source, 200, ['Content-Type' => 'application/zip'])]);
    Queue::fake();
    app()->call([new DiscoverFilingsJob(new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 10)), 'handle']);
    $filing = Filing::query()->findOrFail($filingId);
    app()->call([new DownloadFilingJob($filingId), 'handle']);
    app()->call([new ParseXbrlJob($filingId), 'handle']);

    $filing->refresh();
    $path = $filing->storage_path;
    $storedSource = Storage::disk('local')->get($path);
    $countsAfterFirstRun = [
        'filings' => Filing::query()->count(),
        'artifacts' => FilingArtifact::query()->where('filing_id', $filingId)->count(),
        'facts' => RawFact::query()->where('filing_id', $filingId)->count(),
    ];

    app()->call([new DiscoverFilingsJob(new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 10)), 'handle']);
    app()->call([new DownloadFilingJob($filingId), 'handle']);
    app()->call([new ParseXbrlJob($filingId), 'handle']);

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Normalizing->value)
        ->and([
            'filings' => Filing::query()->count(),
            'artifacts' => FilingArtifact::query()->where('filing_id', $filingId)->count(),
            'facts' => RawFact::query()->where('filing_id', $filingId)->count(),
        ])->toBe($countsAfterFirstRun)
        ->and(Storage::disk('local')->get($path))->toBe($storedSource)
        ->and(PipelineJobRun::query()->where('filing_id', $filingId)->where('stage', PipelineStage::Parsing->value)->count())->toBe(1);

    Queue::assertPushed(DownloadFilingJob::class, 1);
    Queue::assertPushed(ParseXbrlJob::class, 1);
    Queue::assertPushed(NormalizeFactsJob::class, 1);
});

it('records parser diagnostics and stops downstream dispatch on invalid output', function () {
    $filingId = 'FIL-CHECKPOINT-FAIL';
    Storage::fake('local');
    checkpointConfigureDiscovery($filingId);
    Http::fake(['https://example.test/*' => Http::response('invalid-parser-source', 200, ['Content-Type' => 'application/zip'])]);
    Queue::fake();
    Process::fake(fn () => Process::result('{invalid-json', 'parser diagnostics', 0));

    app()->call([new DiscoverFilingsJob(new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 10)), 'handle']);
    app()->call([new DownloadFilingJob($filingId), 'handle']);
    app()->call([new ParseXbrlJob($filingId), 'handle']);

    $jobRun = PipelineJobRun::query()
        ->where('filing_id', $filingId)
        ->where('stage', PipelineStage::Parsing->value)
        ->latest('id')
        ->first();

    expect(Filing::query()->findOrFail($filingId)->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and($jobRun?->status)->toBe('FAILED')
        ->and($jobRun?->error_type)->toContain('TerminalParserException')
        ->and($jobRun?->error_message)->toContain('valid JSON');

    Queue::assertNotPushed(NormalizeFactsJob::class);
});

function checkpointConfigureDiscovery(string $filingId): void
{
    config([
        'financial-pipeline.discovery.disk' => 'local',
        'financial-pipeline.discovery.fixture_path' => 'financial-pipeline/discovery/checkpoint.json',
    ]);
    Storage::disk('local')->put('financial-pipeline/discovery/checkpoint.json', json_encode([[
        'filing_id' => $filingId,
        'issuer_code' => 'ANTM',
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_start' => '2026-01-01',
        'period_end' => '2026-06-30',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => "sha256:{$filingId}",
        'revision_number' => 1,
        'discovered_at' => '2026-09-07T10:00:00+07:00',
    ]], JSON_THROW_ON_ERROR));
}
