<?php

namespace App\Application\Ops\Pipeline;

use App\Application\Ops\Pipeline\Exceptions\RetryPipelineException;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\AuditLog;
use App\Models\PipelineJobRun;
use Illuminate\Support\Facades\DB;

final class RetryFailedPipelineStage
{
    /** @return array{jobRun: PipelineJobRun, jobClass: class-string} */
    public function execute(PipelineJobRun $failedJobRun, string $actorId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($failedJobRun, $actorId, $reason): array {
            $failed = PipelineJobRun::query()->lockForUpdate()->find($failedJobRun->id);
            if ($failed === null) {
                throw new RetryPipelineException('NOT_FOUND', 'The failed pipeline attempt no longer exists.', 404);
            }

            if ($failed->status !== 'FAILED' || $failed->failure_classification !== 'TRANSIENT' || $failed->logical_input_hash === null) {
                throw new RetryPipelineException('NOT_RETRYABLE', 'This pipeline failure is not eligible for retry.');
            }

            $latest = PipelineJobRun::query()
                ->where('pipeline_run_id', $failed->pipeline_run_id)
                ->where('stage', $failed->stage)
                ->orderByDesc('attempt')->orderByDesc('id')->first();
            if ($latest === null || $latest->id !== $failed->id) {
                throw new RetryPipelineException('STALE_ATTEMPT', 'The selected attempt is no longer the latest failed attempt.');
            }

            if (PipelineJobRun::query()->where('filing_id', $failed->filing_id)->where('stage', $failed->stage)->whereIn('status', ['QUEUED', 'RUNNING'])->exists()) {
                throw new RetryPipelineException('ACTIVE_OPERATION', 'A retry for this stage is already active.');
            }

            $jobClass = $this->jobClass($failed->stage);
            $newAttempt = PipelineJobRun::query()->create([
                'pipeline_run_id' => $failed->pipeline_run_id,
                'filing_id' => $failed->filing_id,
                'stage' => $failed->stage,
                'job_class' => $jobClass,
                'queue_name' => (string) config("financial-pipeline.stages.{$failed->stage}.queue", 'default'),
                'attempt' => $failed->attempt + 1,
                'status' => 'QUEUED',
                'idempotency_key' => $failed->idempotency_key,
                'correlation_id' => $failed->correlation_id,
                'logical_input_hash' => $failed->logical_input_hash,
                'failure_classification' => null,
                'failed_job_uuid' => null,
            ]);

            AuditLog::query()->create([
                'actor_id' => $actorId,
                'action' => 'pipeline.stage_retry_requested',
                'entity_type' => PipelineJobRun::class,
                'entity_id' => (string) $newAttempt->id,
                'filing_id' => $failed->filing_id,
                'correlation_id' => $failed->correlation_id,
                'rationale' => trim($reason ?? '') ?: 'Retry requested by Ops operator.',
                'new_value' => ['stage' => $failed->stage, 'attempt_from' => $failed->attempt, 'attempt_to' => $newAttempt->attempt],
            ]);

            dispatch(new $jobClass($failed->filing_id, (int) $newAttempt->attempt))
                ->onQueue((string) config("financial-pipeline.stages.{$failed->stage}.queue", 'default'))
                ->afterCommit();

            return ['jobRun' => $newAttempt, 'jobClass' => $jobClass];
        });
    }

    /** @return class-string */
    private function jobClass(string $stage): string
    {
        return match (strtoupper($stage)) {
            'DOWNLOAD' => DownloadFilingJob::class,
            'PARSE' => ParseXbrlJob::class,
            'NORMALIZE' => NormalizeFactsJob::class,
            'VALIDATE' => ValidateFilingJob::class,
            'PUBLISH' => PublishFilingJob::class,
            default => throw new RetryPipelineException('NOT_RETRYABLE', 'This pipeline stage cannot be retried.'),
        };
    }
}
