<?php

namespace App\Services\Pipeline;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PipelineFailureHandler
{
    public function __construct(private readonly JobExecutionRecorder $executionRecorder) {}

    public function handle(string $filingId, PipelineStage $stage, Throwable $exception, ?PipelineJobRun $jobRun = null): void
    {
        DB::transaction(function () use ($filingId, $stage, $exception, $jobRun): void {
            $filing = Filing::query()->lockForUpdate()->findOrFail($filingId);
            $pipelineRun = PipelineRun::query()->where('filing_id', $filingId)->latest('id')->first();
            $currentJobRun = $jobRun ?? PipelineJobRun::query()
                ->where('filing_id', $filingId)
                ->where('stage', $stage->value)
                ->whereIn('status', ['QUEUED', 'RUNNING'])
                ->latest('id')
                ->first();

            $filing->forceFill(['processing_stage' => PipelineStage::Failed->value])->save();
            $pipelineRun?->forceFill(['status' => 'FAILED', 'finished_at' => now()])->save();

            if ($currentJobRun !== null) {
                $this->executionRecorder->failed($currentJobRun, $exception, [
                    'filing_id' => $filingId,
                    'stage' => $stage->value,
                    'pipeline_run_id' => $pipelineRun?->id,
                    'attempt' => $currentJobRun->attempt,
                ]);
            }

            AuditLog::query()->create([
                'actor_id' => 'system',
                'action' => 'filing.failed',
                'entity_type' => Filing::class,
                'entity_id' => $filingId,
                'new_value' => [
                    'processing_stage' => PipelineStage::Failed->value,
                    'failed_stage' => $stage->value,
                    'error_type' => $exception::class,
                    'error_code' => (string) $exception->getCode(),
                    'attempt' => $currentJobRun?->attempt,
                ],
                'rationale' => 'Pipeline retry attempts were exhausted; downstream dispatch was stopped.',
                'filing_id' => $filingId,
                'correlation_id' => $currentJobRun?->correlation_id ?? $pipelineRun?->correlation_id,
            ]);
        });
    }
}
