<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Services\Pipeline\PipelineFailureHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

uses(RefreshDatabase::class);

it('records queue exhaustion without dispatching downstream work', function () {
    $filing = Filing::query()->create([
        'filing_id' => 'FIL-FAILURE-1',
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/failure.xbrl',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', 'failure'),
        'revision_number' => 1,
        'processing_stage' => PipelineStage::Parsing->value,
        'quality_status' => 'PENDING',
    ]);
    $run = PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'PARSE',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);
    $jobRun = PipelineJobRun::query()->create([
        'pipeline_run_id' => $run->id,
        'filing_id' => $filing->filing_id,
        'stage' => PipelineStage::Parsing->value,
        'job_class' => 'App\\Jobs\\Pipeline\\ParseXbrlJob',
        'queue_name' => 'xbrl',
        'attempt' => 2,
        'status' => 'RUNNING',
        'idempotency_key' => 'failure-key-1',
        'correlation_id' => $run->correlation_id,
    ]);

    app(PipelineFailureHandler::class)->handle(
        $filing->filing_id,
        PipelineStage::Parsing,
        new RuntimeException('Authorization: Bearer secret-token'),
        $jobRun,
    );

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and($run->fresh()->status)->toBe('FAILED')
        ->and($jobRun->fresh()->status)->toBe('FAILED')
        ->and($jobRun->fresh()->error_message)->toContain('[REDACTED]')
        ->and($jobRun->fresh()->error_message)->not->toContain('secret-token')
        ->and($jobRun->fresh()->error_context)->toMatchArray([
            'queue_connection' => 'redis-xbrl',
            'queue_name' => 'xbrl',
        ])
        ->and(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.failed')->exists())->toBeTrue();
});

it('records canonical queue context for every active stage failure', function (PipelineStage $stage, string $connection, string $queue) {
    [$filing, $run, $jobRun] = makeFailureFixture($stage, $queue);

    app(PipelineFailureHandler::class)->handle(
        $filing->filing_id,
        $stage,
        new RuntimeException('Transient worker failure.'),
        $jobRun,
    );

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and($run->fresh()->status)->toBe('FAILED')
        ->and($jobRun->fresh()->status)->toBe('FAILED')
        ->and($jobRun->fresh()->error_context)->toMatchArray([
            'queue_connection' => $connection,
            'queue_name' => $queue,
        ]);
})->with([
    [PipelineStage::Discovered, 'redis-discovery', 'discovery'],
    [PipelineStage::Downloading, 'redis-downloads', 'downloads'],
    [PipelineStage::Parsing, 'redis-xbrl', 'xbrl'],
    [PipelineStage::Normalizing, 'redis-normalize', 'normalize'],
    [PipelineStage::Validating, 'redis-validate', 'validate'],
    [PipelineStage::Publishing, 'redis-publish', 'publish'],
]);

it('records exhausted I/O and compute jobs without downstream dispatch', function (PipelineStage $stage, string $queue, string $jobClass) {
    Queue::fake();
    [$filing, $run, $jobRun] = makeFailureFixture($stage, $queue);

    (new $jobClass($filing->filing_id))->failed(new RuntimeException('Authorization: Bearer exhausted-secret'));

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and($run->fresh()->status)->toBe('FAILED')
        ->and($jobRun->fresh()->status)->toBe('FAILED')
        ->and($jobRun->fresh()->error_message)->not->toContain('exhausted-secret')
        ->and($jobRun->fresh()->error_context)->toMatchArray([
            'queue_connection' => $stage === PipelineStage::Downloading ? 'redis-downloads' : 'redis-xbrl',
            'queue_name' => $queue,
        ]);

    Queue::assertNothingPushed();
})->with([
    [PipelineStage::Downloading, 'downloads', DownloadFilingJob::class],
    [PipelineStage::Parsing, 'xbrl', ParseXbrlJob::class],
]);

/** @return array{0: Filing, 1: PipelineRun, 2: PipelineJobRun} */
function makeFailureFixture(PipelineStage $stage, string $queue): array
{
    $suffix = strtolower($stage->value);
    $filing = Filing::query()->create([
        'filing_id' => 'FIL-FAILURE-'.$suffix,
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/failure.xbrl',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', $suffix),
        'revision_number' => 1,
        'processing_stage' => $stage->value,
        'quality_status' => 'PENDING',
    ]);
    $run = PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => $stage->value,
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);
    $jobRun = PipelineJobRun::query()->create([
        'pipeline_run_id' => $run->id,
        'filing_id' => $filing->filing_id,
        'stage' => $stage->value,
        'job_class' => 'App\\Jobs\\Pipeline\\StageJob',
        'queue_name' => $queue,
        'attempt' => 2,
        'status' => 'RUNNING',
        'idempotency_key' => 'failure-key-'.$suffix,
        'correlation_id' => $run->correlation_id,
    ]);

    return [$filing, $run, $jobRun];
}
