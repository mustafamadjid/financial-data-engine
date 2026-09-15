<?php

namespace App\Application\Ops\ConceptMapping;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Models\AuditLog;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\PipelineRun;
use App\Models\RawFact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReprocessAffectedFilings
{
    /**
     * @param  list<string>  $filingIds
     * @return array{mappingSeriesKey: string, mappingSetVersion: int, acceptedCount: int, runs: list<array<string, mixed>>}
     */
    public function execute(string $mappingSeries, array $filingIds, int $expectedMappingSetVersion, string $reason): array
    {
        $mappingSeries = trim($mappingSeries);
        $reason = trim($reason);
        if ($mappingSeries === '' || $reason === '') {
            throw new ReprocessAffectedFilingsException('VALIDATION_ERROR', 'Mapping series and reason are required.');
        }
        if (count($filingIds) !== count(array_unique($filingIds))) {
            throw new ReprocessAffectedFilingsException('DUPLICATE_FILING_IDS', 'Each filing may only be selected once.');
        }

        $filingIds = array_values(array_unique(array_map('strval', $filingIds)));
        sort($filingIds, SORT_STRING);

        return DB::transaction(function () use ($mappingSeries, $filingIds, $expectedMappingSetVersion, $reason): array {
            $mapping = ConceptMapping::query()
                ->where('mapping_series_key', $mappingSeries)
                ->orderByDesc('rule_version')
                ->orderBy('mapping_rule_id')
                ->lockForUpdate()
                ->first();

            if ($mapping === null) {
                throw new ReprocessAffectedFilingsException('MAPPING_SERIES_NOT_FOUND', 'The requested mapping series was not found.', 404);
            }
            if ((int) $mapping->rule_version !== $expectedMappingSetVersion) {
                throw new ReprocessAffectedFilingsException('MAPPING_SET_VERSION_STALE', 'The mapping set changed since impact preview. Refresh before reprocessing.', 409);
            }
            if ((string) $mapping->status !== 'APPROVED') {
                throw new ReprocessAffectedFilingsException('MAPPING_NOT_APPROVED', 'Only an approved mapping can be reprocessed.');
            }

            $impactIds = $this->freshImpactIds($mapping);
            $outside = array_values(array_diff($filingIds, $impactIds));
            if ($outside !== []) {
                throw new ReprocessAffectedFilingsException('FILING_OUTSIDE_IMPACT', 'One or more filings are no longer in the fresh impact set.', 409);
            }

            $runs = [];
            foreach ($filingIds as $filingId) {
                $filing = Filing::query()->lockForUpdate()->find($filingId);
                if ($filing === null) {
                    throw new ReprocessAffectedFilingsException('FILING_NOT_FOUND', 'A selected filing no longer exists.', 409);
                }
                if (! RawFact::query()->where('filing_id', $filingId)->exists()) {
                    throw new ReprocessAffectedFilingsException('PREREQUISITE_MISSING', 'Every selected filing must contain raw facts.');
                }
                if (PipelineRun::query()->where('filing_id', $filingId)->where('status', 'RUNNING')->exists()) {
                    throw new ReprocessAffectedFilingsException('ACTIVE_OPERATION', 'A selected filing already has an active pipeline run.', 409);
                }
            }

            foreach ($filingIds as $filingId) {
                $filing = Filing::query()->lockForUpdate()->findOrFail($filingId);
                $correlationId = (string) Str::uuid();
                $run = PipelineRun::query()->create([
                    'filing_id' => $filingId,
                    'trigger' => 'REPROCESS',
                    'started_from_stage' => 'NORMALIZE',
                    'status' => 'RUNNING',
                    'initiated_by' => 'system',
                    'reason' => $reason,
                    'dependency_versions' => [
                        'mapping_series_key' => $mappingSeries,
                        'mapping_set_version' => $expectedMappingSetVersion,
                    ],
                    'correlation_id' => $correlationId,
                    'started_at' => now(),
                ]);
                $oldValue = ['processing_stage' => $filing->processing_stage, 'quality_status' => $filing->quality_status];
                $filing->forceFill(['processing_stage' => PipelineStage::Normalizing->value, 'quality_status' => 'PENDING'])->save();
                AuditLog::query()->create([
                    'actor_id' => 'system',
                    'action' => 'concept_mapping.reprocess_requested',
                    'entity_type' => Filing::class,
                    'entity_id' => $filingId,
                    'old_value' => $oldValue,
                    'new_value' => [
                        'pipeline_run_id' => $run->id,
                        'mapping_series_key' => $mappingSeries,
                        'mapping_set_version' => $expectedMappingSetVersion,
                        'processing_stage' => PipelineStage::Normalizing->value,
                    ],
                    'rationale' => $reason,
                    'filing_id' => $filingId,
                    'correlation_id' => $correlationId,
                ]);
                dispatch(new NormalizeFactsJob($filingId))->onQueue((string) config('financial-pipeline.stages.NORMALIZE.queue', 'normalize'))->afterCommit();
                $runs[] = [
                    'pipelineRunId' => $run->id,
                    'filingId' => $filingId,
                    'correlationId' => $correlationId,
                    'stage' => 'NORMALIZE',
                ];
            }

            return [
                'mappingSeriesKey' => $mappingSeries,
                'mappingSetVersion' => (int) $mapping->rule_version,
                'acceptedCount' => count($runs),
                'runs' => $runs,
            ];
        });
    }

    /** @return list<string> */
    private function freshImpactIds(ConceptMapping $mapping): array
    {
        return RawFact::query()
            ->with(['filing', 'context'])
            ->where('source_concept', $mapping->source_concept)
            ->get()
            ->filter(function (RawFact $fact) use ($mapping): bool {
                if ($mapping->entry_point !== null && $fact->filing?->taxonomy_entry_point !== $mapping->entry_point) {
                    return false;
                }
                if (is_array($mapping->allowed_scope) && $mapping->allowed_scope !== [] && ! in_array($fact->context?->scope, $mapping->allowed_scope, true)) {
                    return false;
                }

                return $mapping->period_type === null || $mapping->period_type === $fact->context?->period_type;
            })
            ->pluck('filing_id')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
