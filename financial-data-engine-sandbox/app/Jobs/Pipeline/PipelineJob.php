<?php

namespace App\Jobs\Pipeline;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Services\Pipeline\PipelineExecutionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class PipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $filingId) {}

    /** @param array<string, scalar|null> $versions */
    protected function logExecutionContext(
        PipelineExecutionLogger $logger,
        PipelineJobRun $jobRun,
        PipelineRun $pipelineRun,
        PipelineStage $stage,
        array $versions = [],
    ): void {
        $logger->withContext($logger->context(
            filingId: $this->filingId,
            pipelineRunId: $pipelineRun->id,
            stage: $stage,
            job: static::class,
            queue: (string) $this->queue,
            attempt: (int) $jobRun->attempt,
            correlationId: $jobRun->correlation_id,
            versions: $versions,
        ));
    }
}
