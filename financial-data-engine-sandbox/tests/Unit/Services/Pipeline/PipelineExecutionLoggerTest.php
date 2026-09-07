<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Services\Pipeline\PipelineExecutionLogger;

it('builds structured sanitized pipeline log context', function () {
    $logger = new PipelineExecutionLogger;

    $context = $logger->context(
        filingId: 'FIL-LOG-1',
        pipelineRunId: 42,
        stage: PipelineStage::Publishing,
        job: 'PublishFilingJob',
        queue: 'filing-publish',
        attempt: 2,
        correlationId: 'corr-1',
        versions: ['publish_contract_version' => '1.0.0'],
        extra: ['authorization' => 'Bearer secret-token', 'source_url' => 'https://example.test/file?signature=secret'],
    );

    expect($context)->toMatchArray([
        'filing_id' => 'FIL-LOG-1',
        'pipeline_run_id' => 42,
        'stage' => 'PUBLISHING',
        'job' => 'PublishFilingJob',
        'queue' => 'filing-publish',
        'attempt' => 2,
        'correlation_id' => 'corr-1',
        'publish_contract_version' => '1.0.0',
    ])
        ->and($context['authorization'])->toBe('[REDACTED]')
        ->and($context['source_url'])->toContain('[REDACTED]');
});
