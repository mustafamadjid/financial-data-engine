<?php

namespace App\Jobs\Pipeline;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Normalization\Exceptions\TerminalNormalizationException;
use App\Domain\FinancialData\Normalization\NormalizationService;
use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use App\Models\AuditLog;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\RawFact;
use App\Services\Pipeline\JobExecutionRecorder;
use App\Services\Pipeline\MappingVersionResolver;
use App\Services\Pipeline\NormalizedFilingPersistence;
use App\Services\Pipeline\PipelineExecutionLogger;
use App\Services\Pipeline\PipelineFailureHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class NormalizeFactsJob extends PipelineJob
{
    public int $tries;

    public int $timeout;

    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.NORMALIZE.queue', 'filing-normalize'));
        $this->tries = (int) config('financial-pipeline.stages.NORMALIZE.tries', 3);
        $this->timeout = (int) config('financial-pipeline.stages.NORMALIZE.timeout', 300);
    }

    public function backoff(): array
    {
        return array_values((array) config('financial-pipeline.stages.NORMALIZE.backoff', [30, 120]));
    }

    public function middleware(): array
    {
        return [new PipelineOverlapMiddleware($this->filingId, PipelineStage::Normalizing, $this->timeout)];
    }

    public function handle(
        NormalizationService $normalizationService,
        NormalizedFilingPersistence $persistence,
        MappingVersionResolver $mappingVersionResolver,
        JobExecutionRecorder $executionRecorder,
        PipelineExecutionLogger $pipelineLogger,
        PipelineOrchestrator $orchestrator,
    ): void {
        $filing = Filing::query()->find($this->filingId);

        if ($filing === null) {
            throw new RuntimeException('Filing was not found.');
        }

        $normalizationBaseVersion = trim((string) config('financial-pipeline.normalization.version', '1.0.0'));
        $rawFacts = RawFact::query()
            ->where('filing_id', $filing->filing_id)
            ->with(['filing', 'context', 'unit'])
            ->orderBy('raw_fact_id')
            ->get();
        $rawExtractionVersion = $this->rawExtractionVersion($rawFacts);
        $mappings = $this->mappingsForCurrentVersion();
        $mappingVersion = $mappingVersionResolver->resolve();
        $normalizationVersion = $normalizationBaseVersion.'@'.$mappingVersion;
        $idempotencyKey = PipelineIdempotencyKey::normalize(
            $filing->filing_id,
            $rawExtractionVersion,
            $mappingVersion,
            $normalizationVersion,
        )->value();
        $successfulRun = PipelineJobRun::query()
            ->where('filing_id', $filing->filing_id)
            ->where('stage', PipelineStage::Normalizing->value)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'SUCCEEDED')
            ->exists();

        if (PipelineStage::tryFrom((string) $filing->processing_stage) !== PipelineStage::Normalizing) {
            if ($successfulRun) {
                return;
            }

            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Normalizing,
                new TerminalNormalizationException('Filing is not ready for normalization.'),
            );

            return;
        }

        $pipelineRun = PipelineRun::query()->where('filing_id', $filing->filing_id)->latest('id')->first();

        if ($pipelineRun === null) {
            $pipelineRun = PipelineRun::query()->create([
                'filing_id' => $filing->filing_id,
                'trigger' => 'NORMALIZE',
                'status' => 'RUNNING',
                'correlation_id' => (string) Str::uuid(),
                'started_at' => now(),
            ]);
        }

        $jobRun = $executionRecorder->queued(
            filingId: $filing->filing_id,
            stage: PipelineStage::Normalizing->value,
            jobClass: self::class,
            queueName: (string) $this->queue,
            idempotencyKey: $idempotencyKey,
            pipelineRunId: $pipelineRun->id,
            correlationId: $pipelineRun->correlation_id,
        );
        $executionRecorder->running($jobRun);
        $this->logExecutionContext($pipelineLogger, $jobRun, $pipelineRun, PipelineStage::Normalizing, [
            'raw_extraction_version' => $rawExtractionVersion,
            'mapping_version' => $mappingVersion,
            'normalization_version' => $normalizationVersion,
        ]);

        try {
            $records = [];

            foreach ($rawFacts as $rawFact) {
                $records[] = [
                    'rawFact' => $rawFact,
                    'outcome' => $normalizationService->normalize(
                        $rawFact,
                        $mappings[(string) $rawFact->source_concept] ?? [],
                        $normalizationVersion,
                    ),
                ];
            }

            DB::transaction(function () use ($filing, $records, $normalizationVersion, $persistence, $executionRecorder, $jobRun, $orchestrator, $mappingVersion, $rawExtractionVersion): void {
                $current = Filing::query()->lockForUpdate()->findOrFail($filing->filing_id);
                $summary = $persistence->persist($current, $records, $normalizationVersion, $jobRun->correlation_id);
                $qualityStatus = $summary['review_count'] > 0 ? 'REVIEW_REQUIRED' : 'PENDING';
                $current->forceFill([
                    'processing_stage' => PipelineStage::Normalized->value,
                    'quality_status' => $qualityStatus,
                ])->save();
                $executionRecorder->succeeded($jobRun);

                AuditLog::query()->create([
                    'actor_id' => 'system',
                    'action' => 'filing.normalized',
                    'entity_type' => Filing::class,
                    'entity_id' => $current->filing_id,
                    'new_value' => [
                        'raw_extraction_version' => $rawExtractionVersion,
                        'mapping_version' => $mappingVersion,
                        'normalization_version' => $normalizationVersion,
                        'normalized_count' => $summary['normalized_count'],
                        'review_count' => $summary['review_count'],
                        'quality_status' => $qualityStatus,
                    ],
                    'rationale' => 'Raw facts were normalized using approved, versioned mappings.',
                    'filing_id' => $current->filing_id,
                    'correlation_id' => $jobRun->correlation_id,
                ]);

                $orchestrator->dispatchNext($current->filing_id, PipelineStage::Normalized);
            });
        } catch (TerminalNormalizationException $exception) {
            $orchestrator->markFailed($filing->filing_id, PipelineStage::Normalizing, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(PipelineFailureHandler::class)->handle($this->filingId, PipelineStage::Normalizing, $exception);
        } catch (Throwable) {
            // Do not mask the queue worker's original normalization failure.
        }
    }

    /**
     * @return array<string, list<ConceptMapping>>
     */
    private function mappingsForCurrentVersion(): array
    {
        $query = ConceptMapping::query()->with('canonicalConcept')->orderBy('source_concept')->orderByDesc('rule_version')->orderBy('mapping_rule_id');
        $configuredVersion = config('financial-pipeline.normalization.mapping_version');

        if ($configuredVersion !== null && trim((string) $configuredVersion) !== '') {
            $query->where('rule_version', (int) $configuredVersion);
        }

        $grouped = $query->get()->groupBy(fn (ConceptMapping $mapping): string => (string) $mapping->source_concept);
        $configuredVersion = config('financial-pipeline.normalization.mapping_version');

        return $grouped->map(function ($group) use ($configuredVersion): array {
            if ($configuredVersion !== null && trim((string) $configuredVersion) !== '') {
                return $group->all();
            }

            $latestVersion = $group->max('rule_version');

            return $group->where('rule_version', $latestVersion)->values()->all();
        })->all();
    }

    /**
     * @param  Collection<int, RawFact>  $rawFacts
     */
    private function rawExtractionVersion(Collection $rawFacts): string
    {
        $identity = $rawFacts->map(fn (RawFact $rawFact): string => implode(':', [
            $rawFact->raw_fact_id,
            (string) $rawFact->parser_version,
            (string) $rawFact->parser_config_version,
        ]))->all();

        return $identity === [] ? 'empty' : 'raw_'.substr(hash('sha256', implode('|', $identity)), 0, 32);
    }
}
