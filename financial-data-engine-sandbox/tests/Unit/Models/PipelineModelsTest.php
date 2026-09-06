<?php

use App\Models\FailedJob;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Database\Eloquent\Attributes\Fillable;

it('maps failed jobs to its migration schema', function () {
    $model = new FailedJob;

    expect($model->getTable())->toBe('failed_jobs')
        ->and($model->getKeyName())->toBe('id')
        ->and($model->getIncrementing())->toBeTrue()
        ->and($model->getFillable())->toBe(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
        ->and($model->getCasts())->toMatchArray(['failed_at' => 'datetime'])
        ->and((new ReflectionClass($model))->getAttributes(Fillable::class))->toHaveCount(1);
});

it('maps pipeline job runs to its migration schema', function () {
    $model = new PipelineJobRun;

    expect($model->getTable())->toBe('pipeline_job_runs')
        ->and($model->getKeyName())->toBe('id')
        ->and($model->getFillable())->toBe([
            'pipeline_run_id', 'filing_id', 'stage', 'job_class', 'queue_name', 'attempt',
            'status', 'idempotency_key', 'correlation_id', 'error_type', 'error_code',
            'error_message', 'error_context', 'started_at', 'finished_at',
        ])
        ->and($model->getCasts())->toMatchArray([
            'pipeline_run_id' => 'integer',
            'filing_id' => 'integer',
            'attempt' => 'integer',
            'error_context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ])
        ->and((new ReflectionClass($model))->getAttributes(Fillable::class))->toHaveCount(1);
});

it('relates pipeline job runs to their pipeline run and filing', function () {
    expect(method_exists(PipelineJobRun::class, 'pipelineRun'))->toBeTrue()
        ->and(method_exists(PipelineJobRun::class, 'filing'))->toBeTrue()
        ->and(method_exists(PipelineRun::class, 'jobRuns'))->toBeTrue();
});
