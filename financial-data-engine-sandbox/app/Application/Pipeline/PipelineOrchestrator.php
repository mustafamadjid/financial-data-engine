<?php

namespace App\Application\Pipeline;

use App\Application\Pipeline\Exceptions\InvalidPipelineTransition;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Services\Pipeline\JobExecutionRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class PipelineOrchestrator
{
    public function __construct(private readonly JobExecutionRecorder $executionRecorder) {}

    public function dispatchNext(string $filingId, PipelineStage $completedStage): void
    {
        $filingId = $this->validatedFilingId($filingId);
        $transition = PipelineTransition::fromCompletedStage($completedStage);

        DB::transaction(function () use ($filingId, $completedStage, $transition): void {
            $filing = Filing::query()->lockForUpdate()->find($filingId);

            if ($filing === null) {
                throw (new ModelNotFoundException)->setModel(Filing::class, [$filingId]);
            }

            $currentStage = PipelineStage::tryFrom((string) $filing->processing_stage);

            if ($currentStage !== $completedStage) {
                throw new InvalidPipelineTransition(
                    $currentStage ?? PipelineStage::Failed,
                    $completedStage,
                );
            }

            if ($completedStage === PipelineStage::Validated && ! $this->isPublishEligible($filing)) {
                return;
            }

            $filing->forceFill(['processing_stage' => $transition->nextStage->value])->save();

            dispatch(new $transition->jobClass($filingId))
                ->onQueue($transition->queueName)
                ->afterCommit();
        });
    }

    public function markFailed(string $filingId, PipelineStage $stage, Throwable $error): void
    {
        $filingId = $this->validatedFilingId($filingId);

        DB::transaction(function () use ($filingId, $stage, $error): void {
            $filing = Filing::query()->lockForUpdate()->find($filingId);

            if ($filing === null) {
                throw (new ModelNotFoundException)->setModel(Filing::class, [$filingId]);
            }

            $filing->forceFill(['processing_stage' => PipelineStage::Failed->value])->save();

            $pipelineRun = PipelineRun::query()
                ->where('filing_id', $filingId)
                ->latest('id')
                ->first();

            if ($pipelineRun !== null) {
                $pipelineRun->forceFill([
                    'status' => 'FAILED',
                    'finished_at' => now(),
                ])->save();
            }

            $jobRun = PipelineJobRun::query()
                ->where('filing_id', $filingId)
                ->where('stage', $stage->value)
                ->whereIn('status', ['QUEUED', 'RUNNING'])
                ->latest('id')
                ->first();

            if ($jobRun !== null) {
                $this->executionRecorder->failed($jobRun, $error, [
                    'filing_id' => $filingId,
                    'stage' => $stage->value,
                ]);
            }

            AuditLog::create([
                'actor_id' => 'system',
                'action' => 'filing.failed',
                'entity_type' => Filing::class,
                'entity_id' => $filingId,
                'new_value' => [
                    'processing_stage' => PipelineStage::Failed->value,
                    'failed_stage' => $stage->value,
                    'error_type' => $error::class,
                    'error_code' => (string) $error->getCode(),
                ],
                'rationale' => 'Pipeline stage failed; downstream dispatch was stopped.',
                'filing_id' => $filingId,
                'correlation_id' => $jobRun?->correlation_id ?? $pipelineRun?->correlation_id,
            ]);
        });
    }

    private function isPublishEligible(Filing $filing): bool
    {
        return QualityStatus::tryFrom((string) $filing->quality_status) === QualityStatus::Verified;
    }

    private function validatedFilingId(string $filingId): string
    {
        $filingId = trim($filingId);

        if ($filingId === '' || strlen($filingId) > 128) {
            throw new InvalidArgumentException('A valid filing identifier is required.');
        }

        return $filingId;
    }
}
