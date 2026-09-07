<?php

use App\Models\Filing;
use App\Models\PipelineRun;
use App\Services\Pipeline\JobExecutionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records lifecycle states with attempt and correlation data', function () {
    $recorder = app(JobExecutionRecorder::class);
    Filing::create([
        'filing_id' => 'FIL-001',
        'issuer_code' => 'TEST',
        'period_end' => '2026-06-30',
        'source_url' => 'https://example.test/filing',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:source',
        'revision_number' => 1,
    ]);
    $pipelineRun = PipelineRun::create([
        'filing_id' => 'FIL-001',
        'trigger' => 'DISCOVERY',
        'status' => 'RUNNING',
        'correlation_id' => '22222222-2222-2222-2222-222222222222',
    ]);

    $run = $recorder->queued(
        filingId: 'FIL-001',
        stage: 'PARSE',
        jobClass: 'App\\Jobs\\ParseXbrlJob',
        queueName: 'filing-parse',
        idempotencyKey: 'parse:key',
        attempt: 2,
        pipelineRunId: $pipelineRun->id,
        correlationId: '11111111-1111-1111-1111-111111111111',
    );
    $recorder->running($run);
    $recorder->succeeded($run);

    $run->refresh();

    expect($run->status)->toBe('SUCCEEDED')
        ->and($run->attempt)->toBe(2)
        ->and($run->correlation_id)->toBe('11111111-1111-1111-1111-111111111111')
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull();
});

it('redacts secrets from failure context', function () {
    $recorder = app(JobExecutionRecorder::class);
    Filing::create([
        'filing_id' => 'FIL-002',
        'issuer_code' => 'TEST',
        'period_end' => '2026-06-30',
        'source_url' => 'https://example.test/filing',
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:source',
        'revision_number' => 1,
    ]);
    $pipelineRun = PipelineRun::create([
        'filing_id' => 'FIL-002',
        'trigger' => 'DISCOVERY',
        'status' => 'RUNNING',
        'correlation_id' => '33333333-3333-3333-3333-333333333333',
    ]);
    $run = $recorder->queued('FIL-002', 'PARSE', 'App\\Jobs\\ParseXbrlJob', 'filing-parse', 'parse:key', pipelineRunId: $pipelineRun->id);

    $recorder->failed($run, new RuntimeException('Authorization Bearer super-secret-token'), [
        'api_token' => 'super-secret-token',
        'nested' => ['password' => 'secret-password'],
        'safe' => 'kept',
    ]);

    $run->refresh();

    expect($run->status)->toBe('FAILED')
        ->and($run->error_message)->toContain('[REDACTED]')
        ->and($run->error_message)->not->toContain('super-secret-token')
        ->and($run->error_context)->toMatchArray([
            'api_token' => '[REDACTED]',
            'nested' => ['password' => '[REDACTED]'],
            'safe' => 'kept',
        ]);
});
