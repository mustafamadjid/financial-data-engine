<?php

use App\Application\Pipeline\Exceptions\InvalidPipelineTransition;
use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

function createOrchestratorFiling(
    string $filingId = 'FIL-ORCHESTRATOR',
    PipelineStage $stage = PipelineStage::Discovered,
    QualityStatus $qualityStatus = QualityStatus::Pending,
): Filing {
    return Filing::create([
        'filing_id' => $filingId,
        'issuer_code' => 'TEST',
        'period_end' => '2026-06-30',
        'source_url' => 'https://example.test/filing',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:source',
        'revision_number' => 1,
        'processing_stage' => $stage->value,
        'quality_status' => $qualityStatus->value,
    ]);
}

it('dispatches the next job on its configured connection and queue and advances the processing stage', function (PipelineStage $completedStage, string $jobClass, PipelineStage $nextStage, string $connection, string $queueName) {
    Queue::fake();
    $qualityStatus = $completedStage === PipelineStage::Validated
        ? QualityStatus::Verified
        : QualityStatus::Pending;
    $filing = createOrchestratorFiling(stage: $completedStage, qualityStatus: $qualityStatus);

    app(PipelineOrchestrator::class)->dispatchNext($filing->filing_id, $completedStage);

    Queue::assertPushed($jobClass, function (object $job) use ($filing, $connection, $queueName): bool {
        return $job->filingId === $filing->filing_id
            && $job->connection === $connection
            && $job->queue === $queueName;
    });
    expect($filing->refresh()->processing_stage)->toBe($nextStage->value)
        ->and($filing->quality_status)->toBe($qualityStatus->value);
})->with([
    [PipelineStage::Discovered, DownloadFilingJob::class, PipelineStage::Downloading, 'redis-downloads', 'downloads'],
    [PipelineStage::Downloaded, ParseXbrlJob::class, PipelineStage::Parsing, 'redis-xbrl', 'xbrl'],
    [PipelineStage::Parsed, NormalizeFactsJob::class, PipelineStage::Normalizing, 'redis-normalize', 'normalize'],
    [PipelineStage::Normalized, ValidateFilingJob::class, PipelineStage::Validating, 'redis-validate', 'validate'],
    [PipelineStage::Validated, PublishFilingJob::class, PipelineStage::Publishing, 'redis-publish', 'publish'],
]);

it('does not dispatch before the persistence transaction commits', function () {
    config([
        'queue.default' => 'database',
        'financial-pipeline.stages.DOWNLOAD.connection' => 'database',
    ]);
    $filing = createOrchestratorFiling();
    DB::table('jobs')->delete();

    DB::beginTransaction();
    app(PipelineOrchestrator::class)->dispatchNext($filing->filing_id, PipelineStage::Discovered);

    expect(DB::table('jobs')->count())->toBe(0);

    DB::commit();

    expect(DB::table('jobs')->count())->toBe(1);
});

it('rejects a transition that does not match the filing current stage', function () {
    $filing = createOrchestratorFiling(stage: PipelineStage::Discovered);

    expect(fn () => app(PipelineOrchestrator::class)->dispatchNext($filing->filing_id, PipelineStage::Downloaded))
        ->toThrow(InvalidPipelineTransition::class);
});

it('stops automatic publishing when validation requires review', function (QualityStatus $qualityStatus) {
    Queue::fake();
    $filing = createOrchestratorFiling(
        stage: PipelineStage::Validated,
        qualityStatus: $qualityStatus,
    );

    app(PipelineOrchestrator::class)->dispatchNext($filing->filing_id, PipelineStage::Validated);

    Queue::assertNothingPushed();
})->with([QualityStatus::ReviewRequired, QualityStatus::Failed]);

it('marks the filing, pipeline run, and active job run failed without dispatching downstream work', function () {
    Queue::fake();
    $filing = createOrchestratorFiling(stage: PipelineStage::Parsing);
    $pipelineRun = PipelineRun::create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'AUTOMATIC',
        'status' => 'RUNNING',
        'correlation_id' => '22222222-2222-2222-2222-222222222222',
    ]);
    $jobRun = PipelineJobRun::create([
        'pipeline_run_id' => $pipelineRun->id,
        'filing_id' => $filing->filing_id,
        'stage' => PipelineStage::Parsing->value,
        'job_class' => ParseXbrlJob::class,
        'queue_name' => 'xbrl',
        'attempt' => 1,
        'status' => 'RUNNING',
        'idempotency_key' => 'parse-key',
        'correlation_id' => '11111111-1111-1111-1111-111111111111',
    ]);

    app(PipelineOrchestrator::class)->markFailed(
        $filing->filing_id,
        PipelineStage::Parsing,
        new RuntimeException('Parser process failed'),
    );

    expect($filing->refresh()->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and($pipelineRun->refresh()->status)->toBe('FAILED')
        ->and($pipelineRun->finished_at)->not->toBeNull()
        ->and($jobRun->refresh()->status)->toBe('FAILED')
        ->and($jobRun->error_message)->toBe('Parser process failed');

    Queue::assertNothingPushed();
});
