<?php

use App\Domain\FinancialData\Download\Exceptions\RetryableDownloadException;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('downloads, persists metadata, and dispatches parsing after integrity succeeds', function () {
    Storage::fake('local');
    Http::fake(['https://example.test/*' => Http::response('valid-xbrl', 200, ['Content-Type' => 'application/zip'])]);
    Queue::fake();
    $filing = makeDownloadFiling('FIL-DOWNLOAD-1');

    runDownloadJob($filing);

    $filing->refresh();
    expect($filing->processing_stage)->toBe(PipelineStage::Parsing->value)
        ->and(FilingArtifact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(PipelineJobRun::query()->where('filing_id', $filing->filing_id)->first()?->status)->toBe('SUCCEEDED');

    Queue::assertPushed(ParseXbrlJob::class, fn (ParseXbrlJob $job): bool => $job->filingId === $filing->filing_id);
});

it('is idempotent when the same download job is run again', function () {
    Storage::fake('local');
    Http::fake(['https://example.test/*' => Http::response('same-xbrl', 200, ['Content-Type' => 'application/zip'])]);
    Queue::fake();
    $filing = makeDownloadFiling('FIL-DOWNLOAD-2');

    runDownloadJob($filing);
    $artifactBefore = FilingArtifact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray();
    runDownloadJob($filing->fresh());
    $artifactAfter = FilingArtifact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray();

    expect(FilingArtifact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and($artifactAfter)->toBe($artifactBefore)
        ->and(Queue::pushed(ParseXbrlJob::class))->toHaveCount(1);
});

it('classifies transport failures as retryable', function () {
    Storage::fake('local');
    Http::fake(fn () => throw new ConnectionException('upstream timeout'));
    $filing = makeDownloadFiling('FIL-DOWNLOAD-TIMEOUT');

    expect(fn () => app()->call([new DownloadFilingJob($filing->filing_id), 'handle']))
        ->toThrow(RetryableDownloadException::class);
});

it('marks an empty successful response failed without dispatching parse', function () {
    Storage::fake('local');
    Http::fake(['https://example.test/*' => Http::response('', 200, ['Content-Type' => 'application/zip'])]);
    Queue::fake();
    $filing = makeDownloadFiling('FIL-DOWNLOAD-EMPTY');

    runDownloadJob($filing);

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and(FilingArtifact::query()->where('filing_id', $filing->filing_id)->count())->toBe(0);
    Queue::assertNotPushed(ParseXbrlJob::class);
});

it('uses download queue retry, timeout, backoff, and overlap settings', function () {
    $job = new DownloadFilingJob('FIL-DOWNLOAD-CONFIG');

    expect($job->connection)->toBe('redis-downloads')
        ->and($job->queue)->toBe('downloads')
        ->and($job->tries)->toBe(5)
        ->and($job->timeout)->toBe(300)
        ->and($job->backoff())->toBe([30, 120, 300])
        ->and($job->middleware())->toHaveCount(1);
});

function makeDownloadFiling(string $filingId): Filing
{
    $filing = Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'ANTM',
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_end' => '2026-06-30',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => "sha256:discovered-{$filingId}",
        'revision_number' => 1,
        'discovered_at' => now(),
        'processing_stage' => PipelineStage::Downloading->value,
        'quality_status' => 'PENDING',
    ]);

    PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'DISCOVERY',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);

    return $filing;
}

function runDownloadJob(Filing $filing): void
{
    app()->call([new DownloadFilingJob($filing->filing_id), 'handle']);
}
