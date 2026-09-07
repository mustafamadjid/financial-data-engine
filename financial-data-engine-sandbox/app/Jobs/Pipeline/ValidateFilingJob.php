<?php

namespace App\Jobs\Pipeline;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Domain\FinancialData\Validation\Exceptions\TerminalValidationException;
use App\Domain\FinancialData\Validation\FilingQualityAggregator;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Domain\FinancialData\Validation\ValidationEngine;
use App\Domain\FinancialData\Validation\ValidationEvaluation;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\ValidationResult;
use App\Models\ValidationRule;
use App\Services\Pipeline\JobExecutionRecorder;
use App\Services\Pipeline\MappingVersionResolver;
use App\Services\Pipeline\PipelineExecutionLogger;
use App\Services\Pipeline\PipelineFailureHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ValidateFilingJob extends PipelineJob
{
    public int $tries;

    public int $timeout;

    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.VALIDATE.queue', 'filing-validate'));
        $this->tries = (int) config('financial-pipeline.stages.VALIDATE.tries', 3);
        $this->timeout = (int) config('financial-pipeline.stages.VALIDATE.timeout', 300);
    }

    public function backoff(): array
    {
        return array_values((array) config('financial-pipeline.stages.VALIDATE.backoff', [30, 120]));
    }

    public function middleware(): array
    {
        return [new PipelineOverlapMiddleware($this->filingId, PipelineStage::Validating, $this->timeout)];
    }

    public function handle(
        ValidationEngine $validationEngine,
        FilingQualityAggregator $qualityAggregator,
        MappingVersionResolver $mappingVersionResolver,
        JobExecutionRecorder $executionRecorder,
        PipelineExecutionLogger $pipelineLogger,
        PipelineOrchestrator $orchestrator,
    ): void {
        $filing = Filing::query()->find($this->filingId);

        if ($filing === null) {
            throw new RuntimeException('Filing was not found.');
        }

        $normalizationBaseVersion = (string) config('financial-pipeline.normalization.version', '1.0.0');
        $normalizationVersion = $normalizationBaseVersion.'@'.$mappingVersionResolver->resolve();
        $normalizedFacts = NormalizedFact::query()
            ->where('filing_id', $filing->filing_id)
            ->where('normalization_version', $normalizationVersion)
            ->orderBy('normalized_fact_id')
            ->get();

        if ($normalizedFacts->isEmpty()) {
            $legacyFacts = NormalizedFact::query()
                ->where('filing_id', $filing->filing_id)
                ->where('normalization_version', $normalizationBaseVersion)
                ->orderBy('normalized_fact_id')
                ->get();

            if ($legacyFacts->isNotEmpty()) {
                $normalizedFacts = $legacyFacts;
                $normalizationVersion = $normalizationBaseVersion;
            }
        }
        $normalizationIssues = $this->normalizationIssues($filing, $normalizationVersion);
        $normalizedDatasetVersion = $this->normalizedDatasetVersion($normalizedFacts, $normalizationVersion);
        $ruleSetVersion = $this->ruleSetVersion();
        $idempotencyKey = PipelineIdempotencyKey::validate(
            $filing->filing_id,
            $normalizedDatasetVersion,
            $ruleSetVersion,
        )->value();
        $successfulRun = PipelineJobRun::query()
            ->where('filing_id', $filing->filing_id)
            ->where('stage', PipelineStage::Validating->value)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'SUCCEEDED')
            ->exists();

        if (PipelineStage::tryFrom((string) $filing->processing_stage) !== PipelineStage::Validating) {
            if ($successfulRun) {
                return;
            }

            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Validating,
                new TerminalValidationException('Filing is not ready for validation.'),
            );

            return;
        }

        $pipelineRun = PipelineRun::query()->where('filing_id', $filing->filing_id)->latest('id')->first();

        if ($pipelineRun === null) {
            $pipelineRun = PipelineRun::query()->create([
                'filing_id' => $filing->filing_id,
                'trigger' => 'VALIDATE',
                'status' => 'RUNNING',
                'correlation_id' => (string) Str::uuid(),
                'started_at' => now(),
            ]);
        }

        $jobRun = $executionRecorder->queued(
            filingId: $filing->filing_id,
            stage: PipelineStage::Validating->value,
            jobClass: self::class,
            queueName: (string) $this->queue,
            idempotencyKey: $idempotencyKey,
            pipelineRunId: $pipelineRun->id,
            correlationId: $pipelineRun->correlation_id,
        );
        $executionRecorder->running($jobRun);
        $this->logExecutionContext($pipelineLogger, $jobRun, $pipelineRun, PipelineStage::Validating, [
            'normalized_dataset_version' => $normalizedDatasetVersion,
            'validation_rule_set_version' => $ruleSetVersion,
            'normalization_version' => $normalizationVersion,
        ]);

        try {
            $context = new FilingValidationContext(
                filing: $filing,
                normalizedFacts: $normalizedFacts->all(),
                normalizationIssues: $normalizationIssues,
            );
            $evaluations = $validationEngine->evaluate($context);
            $this->assertEvidenceBelongsToFiling($evaluations, $normalizedFacts);
            $qualityFloor = QualityStatus::tryFrom((string) $filing->quality_status) === QualityStatus::ReviewRequired
                ? QualityStatus::ReviewRequired
                : QualityStatus::Pending;
            $qualityStatus = $qualityAggregator->aggregate($evaluations, $qualityFloor);

            DB::transaction(function () use ($filing, $normalizedFacts, $evaluations, $normalizedDatasetVersion, $ruleSetVersion, $normalizationVersion, $qualityStatus, $executionRecorder, $jobRun, $orchestrator): void {
                $current = Filing::query()->lockForUpdate()->findOrFail($filing->filing_id);

                foreach ($evaluations as $evaluation) {
                    $result = $evaluation->result;
                    ValidationResult::query()->firstOrCreate(
                        ['validation_result_id' => $this->validationResultId($current, $normalizedDatasetVersion, $ruleSetVersion, $evaluation)],
                        [
                            'filing_id' => $current->filing_id,
                            'normalized_dataset_version' => $normalizedDatasetVersion,
                            'validation_rule_set_version' => $ruleSetVersion,
                            'normalized_fact_ids' => $result->normalizedFactIds === [] ? null : $result->normalizedFactIds,
                            'rule_code' => $evaluation->ruleCode,
                            'rule_version' => $evaluation->ruleVersion,
                            'result' => $result->result,
                            'severity' => $result->severity,
                            'message' => $result->message,
                            'expected_value' => $result->expectedValue,
                            'actual_value' => $result->actualValue,
                            'tolerance' => $result->tolerance,
                            'checked_at' => now(),
                        ],
                    );
                }

                $normalizedFacts->each(function (NormalizedFact $normalizedFact) use ($qualityStatus): void {
                    $normalizedFact->forceFill(['validation_status' => $qualityStatus->value])->save();
                });
                $current->forceFill([
                    'processing_stage' => PipelineStage::Validated->value,
                    'quality_status' => $qualityStatus->value,
                ])->save();
                $executionRecorder->succeeded($jobRun);

                if ($qualityStatus === QualityStatus::ReviewRequired) {
                    AuditLog::query()->create([
                        'actor_id' => 'system',
                        'action' => 'filing.review_required',
                        'entity_type' => Filing::class,
                        'entity_id' => $current->filing_id,
                        'new_value' => [
                            'quality_status' => $qualityStatus->value,
                            'normalized_dataset_version' => $normalizedDatasetVersion,
                            'validation_rule_set_version' => $ruleSetVersion,
                        ],
                        'rationale' => 'Validation identified a condition requiring explicit review before publishing.',
                        'filing_id' => $current->filing_id,
                        'correlation_id' => $jobRun->correlation_id,
                    ]);
                }

                AuditLog::query()->create([
                    'actor_id' => 'system',
                    'action' => 'filing.validated',
                    'entity_type' => Filing::class,
                    'entity_id' => $current->filing_id,
                    'new_value' => [
                        'normalized_dataset_version' => $normalizedDatasetVersion,
                        'validation_rule_set_version' => $ruleSetVersion,
                        'normalization_version' => $normalizationVersion,
                        'quality_status' => $qualityStatus->value,
                        'results' => array_map(fn ($evaluation): array => [
                            'rule_code' => $evaluation->ruleCode,
                            'rule_version' => $evaluation->ruleVersion,
                            'result' => $evaluation->result->result,
                            'severity' => $evaluation->result->severity,
                        ], $evaluations),
                    ],
                    'rationale' => 'Normalized facts were evaluated with enabled, versioned validation rules.',
                    'filing_id' => $current->filing_id,
                    'correlation_id' => $jobRun->correlation_id,
                ]);

                $orchestrator->dispatchNext($current->filing_id, PipelineStage::Validated);
            });
        } catch (TerminalValidationException $exception) {
            $orchestrator->markFailed($filing->filing_id, PipelineStage::Validating, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(PipelineFailureHandler::class)->handle($this->filingId, PipelineStage::Validating, $exception);
        } catch (Throwable) {
            // Do not mask the queue worker's original validation failure.
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizationIssues(Filing $filing, string $normalizationVersion): array
    {
        return AuditLog::query()
            ->where('filing_id', $filing->filing_id)
            ->where('action', 'normalization.review_required')
            ->get()
            ->filter(fn (AuditLog $auditLog): bool => ($auditLog->new_value['normalization_version'] ?? null) === $normalizationVersion)
            ->map(fn (AuditLog $auditLog): array => (array) $auditLog->new_value)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, NormalizedFact>  $normalizedFacts
     */
    private function normalizedDatasetVersion(Collection $normalizedFacts, string $normalizationVersion): string
    {
        $identity = $normalizedFacts->map(fn (NormalizedFact $normalizedFact): string => implode(':', [
            $normalizedFact->normalized_fact_id,
            $normalizedFact->raw_fact_id,
            (string) $normalizedFact->mapping_rule_id,
            (string) $normalizedFact->mapping_rule_version,
            (string) $normalizedFact->value,
            $normalizationVersion,
        ]))->all();

        return $identity === [] ? 'empty' : 'normalized_'.substr(hash('sha256', implode('|', $identity)), 0, 32);
    }

    private function ruleSetVersion(): string
    {
        $configuredVersion = trim((string) config('financial-pipeline.validation.rule_set_version', ''));

        if ($configuredVersion !== '') {
            return $configuredVersion;
        }

        $identity = ValidationRule::query()
            ->where('enabled', true)
            ->orderBy('rule_code')
            ->orderBy('rule_version')
            ->get(['rule_code', 'rule_version'])
            ->map(fn (ValidationRule $rule): string => $rule->rule_code.':'.$rule->rule_version)
            ->all();

        return $identity === [] ? 'empty' : 'rules_'.substr(hash('sha256', implode('|', $identity)), 0, 32);
    }

    /**
     * @param  list<ValidationEvaluation>  $evaluations
     * @param  Collection<int, NormalizedFact>  $normalizedFacts
     */
    private function assertEvidenceBelongsToFiling(array $evaluations, Collection $normalizedFacts): void
    {
        $knownIds = $normalizedFacts->pluck('normalized_fact_id')->all();

        foreach ($evaluations as $evaluation) {
            foreach ($evaluation->result->normalizedFactIds as $normalizedFactId) {
                if (! in_array($normalizedFactId, $knownIds, true)) {
                    throw new TerminalValidationException('Validation evidence references a normalized fact outside the filing dataset.');
                }
            }
        }
    }

    private function validationResultId(Filing $filing, string $normalizedDatasetVersion, string $ruleSetVersion, ValidationEvaluation $evaluation): string
    {
        return 'vr_'.substr(hash('sha256', implode('|', [
            $filing->filing_id,
            $normalizedDatasetVersion,
            $ruleSetVersion,
            $evaluation->ruleCode,
            (string) $evaluation->ruleVersion,
        ])), 0, 48);
    }
}
