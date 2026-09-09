<?php

use App\Application\Pipeline\PipelineOrchestrator;
use App\Application\Pipeline\PipelineTransition;
use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Jobs\Pipeline\DiscoverFilingsJob;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\Filing;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('assigns every active pipeline job to its canonical connection and queue', function () {
    $jobs = [
        [new DiscoverFilingsJob(new DiscoveryCriteria('configured', '2026-Q3', 10)), 'redis-discovery', 'discovery'],
        [new DownloadFilingJob('FIL-TOPOLOGY'), 'redis-downloads', 'downloads'],
        [new ParseXbrlJob('FIL-TOPOLOGY'), 'redis-xbrl', 'xbrl'],
        [new NormalizeFactsJob('FIL-TOPOLOGY'), 'redis-normalize', 'normalize'],
        [new ValidateFilingJob('FIL-TOPOLOGY'), 'redis-validate', 'validate'],
        [new PublishFilingJob('FIL-TOPOLOGY'), 'redis-publish', 'publish'],
    ];

    foreach ($jobs as [$job, $connection, $queue]) {
        expect($job->connection)->toBe($connection)
            ->and($job->queue)->toBe($queue);
    }
});

it('keeps reserved workloads out of the active transition map', function () {
    expect(config('financial-pipeline.stages'))->not->toHaveKeys(['ANALYTICS', 'ENRICHMENT'])
        ->and(config('financial-pipeline.reserved_workloads'))->toHaveKeys(['ANALYTICS', 'ENRICHMENT'])
        ->and(config('financial-pipeline.worker_profiles.analytics.processes'))->toBe(0)
        ->and(config('financial-pipeline.worker_profiles.enrichment.processes'))->toBe(0);

    foreach ([
        PipelineStage::Discovered,
        PipelineStage::Downloaded,
        PipelineStage::Parsed,
        PipelineStage::Normalized,
        PipelineStage::Validated,
    ] as $stage) {
        expect(PipelineTransition::fromCompletedStage($stage)->queueName)
            ->not->toBeIn(['analytics', 'enrichment']);
    }
});

it('dispatches downstream work on the active pipeline run correlation context', function () {
    Queue::fake();
    $filing = Filing::create([
        'filing_id' => 'FIL-TOPOLOGY-CORR',
        'issuer_code' => 'TEST',
        'period_end' => '2026-06-30',
        'source_url' => 'https://example.test/filing',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:topology',
        'revision_number' => 1,
        'processing_stage' => PipelineStage::Discovered->value,
        'quality_status' => QualityStatus::Pending->value,
    ]);
    $run = $filing->pipelineRun()->create([
        'trigger' => 'DISCOVERY',
        'status' => 'RUNNING',
        'correlation_id' => '55555555-5555-5555-5555-555555555555',
        'started_at' => now(),
    ]);

    app(PipelineOrchestrator::class)->dispatchNext($filing->filing_id, PipelineStage::Discovered);

    Queue::assertPushed(DownloadFilingJob::class, function (DownloadFilingJob $job) use ($filing, $run): bool {
        return $job->filingId === $filing->filing_id
            && $job->connection === 'redis-downloads'
            && $job->queue === 'downloads'
            && $run->refresh()->correlation_id === '55555555-5555-5555-5555-555555555555';
    });
});
