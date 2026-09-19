<?php

namespace App\Jobs\Pipeline;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Domain\FinancialData\Metadata\FilingMetadataResolver;
use App\Domain\FinancialData\Normalization\Exceptions\TerminalNormalizationException;
use App\Domain\FinancialData\Normalization\NormalizationOutcome;
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

    public function __construct(string $filingId, int $attempt = 1)
    {
        parent::__construct($filingId, $attempt);
        $this->onConnection((string) config('financial-pipeline.stages.NORMALIZE.connection', 'redis-normalize'));
        $this->onQueue((string) config('financial-pipeline.stages.NORMALIZE.queue', 'normalize'));
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
        FilingMetadataResolver $metadataResolver,
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
        $metadataFacts = RawFact::query()
            ->where('filing_id', $filing->filing_id)
            ->with(['filing', 'context.dimensions', 'unit'])
            ->where(function ($query): void {
                $query->where('source_namespace', 'like', '%/dei')->orWhereNull('source_namespace');
            })
            ->orderBy('raw_fact_id')
            ->get();
        $metadata = $metadataResolver->resolve($filing, $metadataFacts);
        if ($metadata->isResolved()) {
            DB::transaction(function () use ($filing, $metadata): void {
                $filing->forceFill(['presentation_currency' => $metadata->presentationCurrency])->save();
                $filing->xbrlContexts()->update(['scope' => $metadata->scope]);
            });
            $metadataFacts->each(function (RawFact $rawFact) use ($metadata): void {
                if ($rawFact->relationLoaded('context') && $rawFact->context !== null) {
                    $rawFact->context->scope = $metadata->scope;
                }
            });
        }
        $rawExtractionVersion = $this->rawExtractionVersion($filing->filing_id);
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
            attempt: $this->attempt,
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
            $summary = ['normalized_count' => 0, 'review_count' => 0];
            $batch = [];
            $batchCount = 0;
            $startedAt = microtime(true);
            $batchSize = max(1, (int) config('financial-pipeline.normalization.batch_size', 1000));
            foreach (RawFact::query()->where('filing_id', $filing->filing_id)->with(['filing', 'context.dimensions', 'unit'])->orderBy('raw_fact_id')->lazyById($batchSize, 'raw_fact_id') as $rawFact) {
                $batch[] = [
                    'rawFact' => $rawFact,
                    'outcome' => $normalizationService->normalize($rawFact, $mappings[(string) $rawFact->source_concept] ?? [], $normalizationVersion),
                ];
                if (count($batch) >= $batchSize) {
                    $summary = $this->persistBatch($filing, $batch, $normalizationVersion, $persistence, $jobRun->correlation_id, $summary);
                    $batchCount++;
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $summary = $this->persistBatch($filing, $batch, $normalizationVersion, $persistence, $jobRun->correlation_id, $summary);
                $batchCount++;
            }

            DB::transaction(function () use ($filing, $summary, $batchCount, $startedAt, $normalizationVersion, $executionRecorder, $jobRun, $orchestrator, $mappingVersion, $rawExtractionVersion, $metadata): void {
                $current = Filing::query()->lockForUpdate()->findOrFail($filing->filing_id);
                $metadataReview = $metadata->status === 'REVIEW_REQUIRED' && $metadata->evidenceRawFactIds !== [];
                $qualityStatus = $summary['review_count'] > 0 || $metadataReview ? 'REVIEW_REQUIRED' : 'PENDING';
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
                        'batch_count' => $batchCount,
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
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

        $selected = $grouped->map(function ($group) use ($configuredVersion): array {
            if ($configuredVersion !== null && trim((string) $configuredVersion) !== '') {
                return $group->all();
            }

            return $group
                ->groupBy(fn (ConceptMapping $mapping): string => (string) ($mapping->mapping_series_key ?: MappingSeriesKey::from((string) $mapping->source_concept, $mapping->entry_point)))
                ->flatMap(function ($series): array {
                    $latestVersion = $series->max('rule_version');

                    return $series->where('rule_version', $latestVersion)->values()->all();
                })
                ->values()
                ->all();
        });

        return $selected->all();
    }

    /**
     * @param  Collection<int, RawFact>  $rawFacts
     */
    private function rawExtractionVersion(string $filingId): string
    {
        $identity = [];
        foreach (RawFact::query()->where('filing_id', $filingId)->orderBy('raw_fact_id')->cursor() as $rawFact) {
            $identity[] = implode(':', [
                $rawFact->raw_fact_id,
                (string) $rawFact->parser_version,
                (string) $rawFact->parser_config_version,
            ]);
        }

        return $identity === [] ? 'empty' : 'raw_'.substr(hash('sha256', implode('|', $identity)), 0, 32);
    }

    /** @param list<array{rawFact: RawFact, outcome: NormalizationOutcome}> $records @param array{normalized_count:int,review_count:int} $summary @return array{normalized_count:int,review_count:int} */
    private function persistBatch(Filing $filing, array $records, string $normalizationVersion, NormalizedFilingPersistence $persistence, ?string $correlationId, array $summary): array
    {
        $batchSummary = DB::transaction(fn (): array => $persistence->persist($filing, $records, $normalizationVersion, $correlationId));

        return [
            'normalized_count' => $summary['normalized_count'] + $batchSummary['normalized_count'],
            'review_count' => $summary['review_count'] + $batchSummary['review_count'],
        ];
    }
}
