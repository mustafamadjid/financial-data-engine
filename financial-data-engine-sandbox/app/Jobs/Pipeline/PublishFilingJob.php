<?php

namespace App\Jobs\Pipeline;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Publishing\Exceptions\InvalidPublishContractException;
use App\Domain\FinancialData\Publishing\Exceptions\PublishNotEligibleException;
use App\Domain\FinancialData\Publishing\FilingPublishPayloadBuilder;
use App\Domain\FinancialData\Publishing\PublishContractValidator;
use App\Domain\FinancialData\Publishing\PublishEligibilityPolicy;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Services\Pipeline\JobExecutionRecorder;
use App\Services\Pipeline\PipelineExecutionLogger;
use App\Services\Pipeline\PipelineFailureHandler;
use App\Services\Pipeline\PublishedSnapshotPersistence;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PublishFilingJob extends PipelineJob
{
    public int $tries;

    public int $timeout;

    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.PUBLISH.queue', 'filing-publish'));
        $this->tries = (int) config('financial-pipeline.stages.PUBLISH.tries', 3);
        $this->timeout = (int) config('financial-pipeline.stages.PUBLISH.timeout', 120);
    }

    public function backoff(): array
    {
        return array_values((array) config('financial-pipeline.stages.PUBLISH.backoff', [30, 120]));
    }

    public function middleware(): array
    {
        return [new PipelineOverlapMiddleware($this->filingId, PipelineStage::Publishing, $this->timeout)];
    }

    public function handle(
        PublishEligibilityPolicy $eligibilityPolicy,
        FilingPublishPayloadBuilder $payloadBuilder,
        PublishContractValidator $contractValidator,
        PublishedSnapshotPersistence $snapshotPersistence,
        JobExecutionRecorder $executionRecorder,
        PipelineExecutionLogger $pipelineLogger,
        PipelineOrchestrator $orchestrator,
    ): void {
        $filing = Filing::query()->find($this->filingId);

        if ($filing === null) {
            throw new RuntimeException('Filing was not found.');
        }

        $pipelineRun = PipelineRun::query()->where('filing_id', $filing->filing_id)->latest('id')->first();

        if ($pipelineRun === null) {
            throw new RuntimeException('A pipeline run is required for publishing.');
        }

        $contractVersion = (string) config('financial-pipeline.publish.contract_version', '1.0.0');
        $idempotencyKey = PipelineIdempotencyKey::publish(
            $filing->filing_id,
            (string) $pipelineRun->id,
            $contractVersion,
        )->value();
        $successfulRun = PipelineJobRun::query()
            ->where('filing_id', $filing->filing_id)
            ->where('stage', PipelineStage::Publishing->value)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'SUCCEEDED')
            ->exists();

        if (PipelineStage::tryFrom((string) $filing->processing_stage) !== PipelineStage::Publishing) {
            if ($successfulRun) {
                return;
            }

            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Publishing,
                new PublishNotEligibleException('Filing is not ready for publishing.'),
            );

            return;
        }

        $jobRun = $executionRecorder->queued(
            filingId: $filing->filing_id,
            stage: PipelineStage::Publishing->value,
            jobClass: self::class,
            queueName: (string) $this->queue,
            idempotencyKey: $idempotencyKey,
            pipelineRunId: $pipelineRun->id,
            correlationId: $pipelineRun->correlation_id,
        );
        $executionRecorder->running($jobRun);
        $this->logExecutionContext($pipelineLogger, $jobRun, $pipelineRun, PipelineStage::Publishing, [
            'publish_contract_version' => $contractVersion,
        ]);

        try {
            $eligibility = $eligibilityPolicy->check($filing);

            if (! $eligibility->allowed()) {
                throw new PublishNotEligibleException($eligibility->reason());
            }

            $payload = $payloadBuilder->build($filing);
            $contractValidator->validate($payload);

            DB::transaction(function () use ($filing, $pipelineRun, $payload, $idempotencyKey, $snapshotPersistence, $executionRecorder, $jobRun): void {
                $current = Filing::query()->lockForUpdate()->findOrFail($filing->filing_id);
                $snapshot = $snapshotPersistence->persist($current, $payload, $idempotencyKey, $pipelineRun->id);
                $current->forceFill(['processing_stage' => PipelineStage::Published->value])->save();
                $pipelineRun->forceFill(['status' => 'SUCCEEDED', 'finished_at' => now()])->save();
                $executionRecorder->succeeded($jobRun);

                AuditLog::query()->create([
                    'actor_id' => 'system',
                    'action' => 'filing.published',
                    'entity_type' => $snapshot::class,
                    'entity_id' => $snapshot->snapshot_id,
                    'new_value' => [
                        'filing_id' => $current->filing_id,
                        'revision_number' => $snapshot->revision_number,
                        'snapshot_id' => $snapshot->snapshot_id,
                        'publish_contract_version' => $snapshot->publish_contract_version,
                        'normalized_dataset_version' => $snapshot->normalized_dataset_version,
                        'validation_rule_set_version' => $snapshot->validation_rule_set_version,
                    ],
                    'rationale' => 'Verified filing was published as an immutable versioned snapshot.',
                    'filing_id' => $current->filing_id,
                    'correlation_id' => $jobRun->correlation_id,
                ]);
            });
        } catch (PublishNotEligibleException|InvalidPublishContractException $exception) {
            $orchestrator->markFailed($filing->filing_id, PipelineStage::Publishing, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(PipelineFailureHandler::class)->handle($this->filingId, PipelineStage::Publishing, $exception);
        } catch (Throwable) {
            // Do not mask the queue worker's original publish failure.
        }
    }
}
