<?php

namespace App\Domain\FinancialData\Discovery;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DiscoverFilingsJob;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineRun;
use App\Services\Pipeline\JobExecutionRecorder;
use App\Services\Pipeline\PipelineExecutionLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FilingDiscoveryService
{
    public function __construct(
        private readonly JobExecutionRecorder $executionRecorder,
        private readonly PipelineOrchestrator $orchestrator,
        private readonly PipelineExecutionLogger $pipelineLogger,
    ) {}

    public function persist(
        DiscoveredFilingData $candidate,
        string $sourceAdapter,
        string $discoveryWindow,
    ): bool {
        if ($sourceAdapter === '' || $discoveryWindow === '') {
            throw new InvalidArgumentException('Discovery source and window are required.');
        }

        return DB::transaction(function () use ($candidate, $sourceAdapter, $discoveryWindow): bool {
            $existing = Filing::query()->lockForUpdate()->find($candidate->filingId);

            if ($existing !== null) {
                if ((int) $existing->revision_number === $candidate->revisionNumber) {
                    return false;
                }

                throw new InvalidArgumentException('A filing identifier cannot be reused for another revision.');
            }

            if ($candidate->supersedesFilingId !== null) {
                $superseded = Filing::query()->find($candidate->supersedesFilingId);

                if ($superseded === null || (int) $superseded->revision_number >= $candidate->revisionNumber) {
                    throw new InvalidArgumentException('Superseded filing reference is invalid.');
                }
            }

            $filing = Filing::query()->create($candidate->toFilingAttributes());
            $pipelineRun = PipelineRun::query()->create([
                'filing_id' => $filing->filing_id,
                'trigger' => 'DISCOVERY',
                'status' => 'RUNNING',
                'correlation_id' => (string) Str::uuid(),
                'started_at' => now(),
            ]);
            $jobRun = $this->executionRecorder->queued(
                filingId: $filing->filing_id,
                stage: PipelineStage::Discovered->value,
                jobClass: DiscoverFilingsJob::class,
                queueName: (string) config('financial-pipeline.stages.DISCOVER.queue', 'discovery'),
                idempotencyKey: PipelineIdempotencyKey::discovery(
                    $sourceAdapter,
                    $discoveryWindow,
                    $candidate->stableCandidateIdentity(),
                )->value(),
                pipelineRunId: $pipelineRun->id,
                correlationId: $pipelineRun->correlation_id,
            );
            $this->executionRecorder->running($jobRun);
            $this->pipelineLogger->withContext($this->pipelineLogger->context(
                filingId: $filing->filing_id,
                pipelineRunId: $pipelineRun->id,
                stage: PipelineStage::Discovered,
                job: DiscoverFilingsJob::class,
                connection: (string) config('financial-pipeline.stages.DISCOVER.connection', 'redis-discovery'),
                queue: (string) $jobRun->queue_name,
                attempt: (int) $jobRun->attempt,
                correlationId: $jobRun->correlation_id,
            ));
            $this->executionRecorder->succeeded($jobRun);

            AuditLog::query()->create([
                'actor_id' => 'system',
                'action' => 'filing.discovered',
                'entity_type' => Filing::class,
                'entity_id' => $filing->filing_id,
                'new_value' => [
                    'revision_number' => $filing->revision_number,
                    'source_adapter' => $sourceAdapter,
                    'source_type' => $filing->source_type,
                ],
                'rationale' => 'Filing discovered from configured source.',
                'filing_id' => $filing->filing_id,
                'correlation_id' => $pipelineRun->correlation_id,
            ]);

            $this->orchestrator->dispatchNext($filing->filing_id, PipelineStage::Discovered);

            return true;
        });
    }
}
