<?php

use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DiscoverFilingsJob;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('persists a new candidate, records discovery execution, and dispatches download', function () {
    configureDiscoveryFixture([discoveryJobCandidate('FIL-001', 'ANTM')]);
    Queue::fake();

    runDiscoveryJob();

    $filing = Filing::query()->find('FIL-001');
    $pipelineRun = PipelineRun::query()->where('filing_id', 'FIL-001')->first();
    $jobRun = PipelineJobRun::query()->where('filing_id', 'FIL-001')->first();

    expect($filing)->not->toBeNull()
        ->and($filing->processing_stage)->toBe(PipelineStage::Downloading->value)
        ->and($pipelineRun?->trigger)->toBe('DISCOVERY')
        ->and($jobRun?->status)->toBe('SUCCEEDED')
        ->and($jobRun?->stage)->toBe(PipelineStage::Discovered->value);

    Queue::assertPushed(DownloadFilingJob::class, fn (DownloadFilingJob $job): bool => $job->filingId === 'FIL-001');
});

it('is idempotent for the same filing revision and does not dispatch duplicate download work', function () {
    configureDiscoveryFixture([discoveryJobCandidate('FIL-001', 'ANTM')]);
    Queue::fake();

    runDiscoveryJob();
    runDiscoveryJob();

    expect(Filing::query()->count())->toBe(1)
        ->and(PipelineRun::query()->count())->toBe(1)
        ->and(PipelineJobRun::query()->count())->toBe(1);

    Queue::assertPushed(DownloadFilingJob::class, 1);
});

it('records canonical discovery queue context in structured logs', function () {
    configureDiscoveryFixture([discoveryJobCandidate('FIL-LOG-DISCOVERY', 'ANTM')]);
    Queue::fake();
    Log::spy();

    runDiscoveryJob();

    Log::shouldHaveReceived('withContext')
        ->once()
        ->withArgs(fn (array $context): bool => $context['filing_id'] === 'FIL-LOG-DISCOVERY'
            && $context['stage'] === PipelineStage::Discovered->value
            && $context['job'] === DiscoverFilingsJob::class
            && $context['connection'] === 'redis-discovery'
            && $context['queue'] === 'discovery'
            && $context['attempt'] === 1
            && isset($context['pipeline_run_id'], $context['correlation_id']));
});

it('preserves the previous filing when a new revision candidate is discovered', function () {
    configureDiscoveryFixture([
        discoveryJobCandidate('FIL-001', 'ANTM'),
        discoveryJobCandidate('FIL-001-R2', 'ANTM', revisionNumber: 2, supersedesFilingId: 'FIL-001'),
    ]);
    Queue::fake();

    runDiscoveryJob();

    expect(Filing::query()->count())->toBe(2)
        ->and(Filing::query()->find('FIL-001')->source_hash)->toBe('sha256:FIL-001')
        ->and(Filing::query()->find('FIL-001-R2')->supersedes_filing_id)->toBe('FIL-001');

    Queue::assertPushed(DownloadFilingJob::class, 2);
});

it('continues processing valid candidates when fixture metadata is invalid', function () {
    configureDiscoveryFixture([
        ['filing_id' => '../invalid'],
        discoveryJobCandidate('FIL-VALID', 'ANTM'),
    ]);
    Queue::fake();

    runDiscoveryJob();

    expect(Filing::query()->pluck('filing_id')->all())->toBe(['FIL-VALID']);
    Queue::assertPushed(DownloadFilingJob::class, 1);
});

it('uses discovery queue retry settings and overlap middleware', function () {
    $job = new DiscoverFilingsJob(new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 10));

    expect($job->connection)->toBe('redis-discovery')
        ->and($job->queue)->toBe('discovery')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->backoff())->toBe([30, 120])
        ->and($job->middleware())->toHaveCount(1);
});

function configureDiscoveryFixture(array $candidates): void
{
    Storage::fake('local');
    config([
        'financial-pipeline.discovery.disk' => 'local',
        'financial-pipeline.discovery.fixture_path' => 'financial-pipeline/discovery/candidates.json',
    ]);
    Storage::disk('local')->put('financial-pipeline/discovery/candidates.json', json_encode($candidates, JSON_THROW_ON_ERROR));
}

function runDiscoveryJob(): void
{
    $job = new DiscoverFilingsJob(new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q2', pageSize: 100));
    app()->call([$job, 'handle']);
}

function discoveryJobCandidate(string $filingId, string $issuerCode, int $revisionNumber = 1, ?string $supersedesFilingId = null): array
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
