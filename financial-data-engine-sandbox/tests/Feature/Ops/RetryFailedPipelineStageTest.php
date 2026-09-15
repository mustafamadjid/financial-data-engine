<?php

use App\Jobs\Pipeline\DownloadFilingJob;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('accepts a transient latest failure while preserving run identity and incrementing attempt', function (): void {
    Queue::fake();
    [$filing, $run, $failed] = retryFixture();

    $response = $this->postJson("/ops/actions/pipeline-job-runs/{$failed->id}/retry", ['reason' => 'Transient provider timeout.']);

    $response->assertStatus(202)
        ->assertJsonPath('data.operation', 'retry')
        ->assertJsonPath('data.filingId', $filing->filing_id)
        ->assertJsonPath('data.pipelineRunId', $run->id)
        ->assertJsonPath('data.correlationId', $run->correlation_id)
        ->assertJsonPath('data.stage', 'DOWNLOAD')
        ->assertJsonPath('data.attempt', 2);

    expect(PipelineJobRun::query()->where('pipeline_run_id', $run->id)->where('stage', 'DOWNLOAD')->count())->toBe(2);
    expect(PipelineJobRun::query()->where('pipeline_run_id', $run->id)->where('attempt', 2)->value('correlation_id'))->toBe($run->correlation_id);
    expect(AuditLog::query()->where('action', 'pipeline.stage_retry_requested')->where('filing_id', $filing->filing_id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'pipeline.stage_retry_requested')->where('filing_id', $filing->filing_id)->value('actor_id'))->toBe('system');
    Queue::assertPushed(DownloadFilingJob::class);
});

it('rejects stale and overlapping retry attempts atomically', function (): void {
    Queue::fake();
    [$filing, $run, $failed] = retryFixture();
    $newer = $failed->replicate(['id']);
    $newer->attempt = 2;
    $newer->save();

    $this->postJson("/ops/actions/pipeline-job-runs/{$failed->id}/retry")
        ->assertStatus(409)->assertJsonPath('code', 'STALE_ATTEMPT');
    $newer->forceFill(['status' => 'QUEUED'])->save();
    $this->postJson("/ops/actions/pipeline-job-runs/{$newer->id}/retry")
        ->assertStatus(409)->assertJsonPath('code', 'NOT_RETRYABLE');
    expect($filing->exists && $run->exists)->toBeTrue();
    Queue::assertNothingPushed();
});

it('rejects a non transient failure without dispatching', function (): void {
    Queue::fake();
    [, , $failed] = retryFixture(['failure_classification' => 'TERMINAL']);

    $this->postJson("/ops/actions/pipeline-job-runs/{$failed->id}/retry")
        ->assertStatus(409)
        ->assertJsonPath('code', 'NOT_RETRYABLE');

    Queue::assertNothingPushed();
});

it('allows a public retry only when the failure is domain-eligible', function (): void {
    Queue::fake();
    [, , $failed] = retryFixture();

    $this->postJson("/ops/actions/pipeline-job-runs/{$failed->id}/retry")
        ->assertAccepted();
});

/** @return array{0: Filing, 1: PipelineRun, 2: PipelineJobRun} */
function retryFixture(array $overrides = []): array
{
    $filing = Filing::query()->create([
        'filing_id' => 'FIL-RETRY-001', 'issuer_code' => 'HSSA', 'report_type' => 'ANNUAL',
        'fiscal_year' => 2025, 'fiscal_period' => 'FY', 'period_end' => '2025-12-31',
        'source_url' => 'https://example.test/retry', 'source_type' => 'XBRL_INSTANCE',
        'source_hash' => str_repeat('a', 64), 'revision_number' => 1, 'processing_stage' => 'FAILED',
        'quality_status' => 'REVIEW_REQUIRED',
    ]);
    $run = PipelineRun::query()->create([
        'filing_id' => $filing->filing_id, 'trigger' => 'AUTOMATIC', 'status' => 'FAILED',
        'correlation_id' => '33333333-3333-3333-3333-333333333333', 'started_at' => now()->subMinute(),
    ]);
    $failed = PipelineJobRun::query()->create(array_merge([
        'pipeline_run_id' => $run->id, 'filing_id' => $filing->filing_id, 'stage' => 'DOWNLOAD',
        'job_class' => DownloadFilingJob::class, 'queue_name' => 'downloads', 'attempt' => 1,
        'status' => 'FAILED', 'idempotency_key' => 'retry-key', 'correlation_id' => $run->correlation_id,
        'error_code' => 'UPSTREAM_TIMEOUT', 'error_message' => 'Provider timed out.',
        'failure_classification' => 'TRANSIENT', 'logical_input_hash' => hash('sha256', 'retry-input'),
        'finished_at' => now()->subSeconds(30),
    ], $overrides));

    return [$filing, $run, $failed];
}
