<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Services\Pipeline\PipelineFailureHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'queue_name' => 'filing-parse',
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
        ->and(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.failed')->exists())->toBeTrue();
});
