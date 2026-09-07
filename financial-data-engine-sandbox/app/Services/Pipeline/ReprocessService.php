<?php

namespace App\Services\Pipeline;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\ReprocessStage;
use App\Infrastructure\Storage\FilingArtifactStorage;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineRun;
use App\Models\RawFact;
use App\Models\ValidationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReprocessService
{
    public function __construct(
        private readonly MappingVersionResolver $mappingVersionResolver,
        private readonly FilingArtifactStorage $artifactStorage,
    ) {}

    public function start(string $filingId, ReprocessStage $stage, string $reason, string $actorId = 'system'): PipelineRun
    {
        $filingId = trim($filingId);
        $reason = trim($reason);
        $actorId = trim($actorId) === '' ? 'system' : trim($actorId);

        if ($filingId === '' || $reason === '') {
            throw new InvalidArgumentException('Filing identifier and reprocess reason are required.');
        }

        return DB::transaction(function () use ($filingId, $stage, $reason, $actorId): PipelineRun {
            $filing = Filing::query()->lockForUpdate()->find($filingId);

            if ($filing === null) {
                throw new InvalidArgumentException('Filing was not found.');
            }

            $this->assertPrerequisite($filing, $stage);

            if (PipelineRun::query()
                ->where('filing_id', $filingId)
                ->where('started_from_stage', $stage->value)
                ->where('status', 'RUNNING')
                ->exists()) {
                throw new InvalidArgumentException('A reprocess run for this filing and stage is already active.');
            }

            [$processingStage, $job, $queueStage] = $this->dispatchDefinition($stage);
            $run = PipelineRun::query()->create([
                'filing_id' => $filingId,
                'trigger' => 'REPROCESS',
                'started_from_stage' => $stage->value,
                'status' => 'RUNNING',
                'initiated_by' => $actorId,
                'reason' => $reason,
                'dependency_versions' => $this->dependencyVersions(),
                'correlation_id' => (string) Str::uuid(),
                'started_at' => now(),
            ]);
            $filing->forceFill([
                'processing_stage' => $processingStage->value,
                'quality_status' => $stage === ReprocessStage::Publish
                    ? $filing->quality_status
                    : 'PENDING',
            ])->save();

            foreach ([
                ['action' => 'filing.reprocess_requested', 'new_value' => ['pipeline_run_id' => $run->id, 'started_from_stage' => $stage->value, 'dependency_versions' => $run->dependency_versions]],
                ['action' => 'filing.reprocess_started', 'new_value' => ['pipeline_run_id' => $run->id, 'processing_stage' => $processingStage->value]],
            ] as $event) {
                AuditLog::query()->create([
                    'actor_id' => $actorId,
                    'action' => $event['action'],
                    'entity_type' => Filing::class,
                    'entity_id' => $filingId,
                    'new_value' => $event['new_value'],
                    'rationale' => $reason,
                    'filing_id' => $filingId,
                    'correlation_id' => $run->correlation_id,
                ]);
            }

            dispatch(new $job($filingId))
                ->onQueue((string) config("financial-pipeline.stages.{$queueStage}.queue"))
                ->afterCommit();

            return $run;
        });
    }

    private function assertPrerequisite(Filing $filing, ReprocessStage $stage): void
    {
        $message = match ($stage) {
            ReprocessStage::Download => trim((string) $filing->source_url) === '' ? 'Filing source locator is required.' : null,
            ReprocessStage::Parse => $this->hasValidArtifact($filing) ? null : 'A valid immutable artifact is required.',
            ReprocessStage::Normalize => RawFact::query()->where('filing_id', $filing->filing_id)->exists() ? null : 'Raw facts are required.',
            ReprocessStage::Validate => NormalizedFact::query()->where('filing_id', $filing->filing_id)->exists() ? null : 'Normalized facts are required.',
            ReprocessStage::Publish => $filing->quality_status === 'VERIFIED' && ValidationResult::query()->where('filing_id', $filing->filing_id)->exists()
                ? null
                : 'A verified validation result is required.',
        };

        if ($message !== null) {
            throw new InvalidArgumentException($message);
        }
    }

    private function hasValidArtifact(Filing $filing): bool
    {
        return $filing->storage_path !== null
            && $this->artifactStorage->validArtifactForFiling($filing) !== null;
    }

    /** @return array{0: PipelineStage, 1: class-string, 2: string} */
    private function dispatchDefinition(ReprocessStage $stage): array
    {
        return match ($stage) {
            ReprocessStage::Download => [PipelineStage::Downloading, DownloadFilingJob::class, 'DOWNLOAD'],
            ReprocessStage::Parse => [PipelineStage::Parsing, ParseXbrlJob::class, 'PARSE'],
            ReprocessStage::Normalize => [PipelineStage::Normalizing, NormalizeFactsJob::class, 'NORMALIZE'],
            ReprocessStage::Validate => [PipelineStage::Validating, ValidateFilingJob::class, 'VALIDATE'],
            ReprocessStage::Publish => [PipelineStage::Publishing, PublishFilingJob::class, 'PUBLISH'],
        };
    }

    /** @return array<string, string> */
    private function dependencyVersions(): array
    {
        return [
            'parser_version' => (string) config('financial-pipeline.parser.version', '1.0.0'),
            'parser_config_version' => (string) config('financial-pipeline.parser.config_version', '1.0.0'),
            'normalization_version' => (string) config('financial-pipeline.normalization.version', '1.0.0'),
            'mapping_version' => $this->mappingVersionResolver->resolve(),
            'validation_rule_set_version' => (string) config('financial-pipeline.validation.rule_set_version', 'auto'),
            'publish_contract_version' => (string) config('financial-pipeline.publish.contract_version', '1.0.0'),
        ];
    }
}
