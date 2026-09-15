<?php

namespace App\Application\Ops\ConceptMapping;

use App\Domain\FinancialData\Mapping\MappingSeriesKey;
use App\Models\AuditLog;
use App\Models\ConceptMapping;
use App\Models\EvidenceRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateMappingVersion
{
    /**
     * @param  array{
     *     source_concept: string,
     *     entry_point: string|null,
     *     canonical_concept: string,
     *     allowed_scope: array<int, string>|null,
     *     period_type: string|null,
     *     sign_convention: string|null,
     *     status: string,
     *     rationale: string,
     *     evidence_ids: array<int, string>|null,
     *     expected_version: int|null
     * }  $attributes
     */
    public function execute(string $mappingSeries, array $attributes): ConceptMapping
    {
        $sourceConcept = trim($attributes['source_concept']);
        $entryPoint = $attributes['entry_point'] === null ? null : trim($attributes['entry_point']);
        $derivedSeries = MappingSeriesKey::from($sourceConcept, $entryPoint);

        if (! hash_equals($derivedSeries, $mappingSeries)) {
            throw new MappingMutationException(
                'MAPPING_SERIES_MISMATCH',
                'The source concept and entry point do not identify the requested mapping series.',
            );
        }

        try {
            return DB::transaction(function () use ($mappingSeries, $attributes, $sourceConcept, $entryPoint): ConceptMapping {
                $latest = ConceptMapping::query()
                    ->where('mapping_series_key', $mappingSeries)
                    ->orderByDesc('rule_version')
                    ->orderByDesc('mapping_rule_id')
                    ->lockForUpdate()
                    ->first();

                $expectedVersion = $attributes['expected_version'];
                $currentVersion = $latest?->rule_version;
                if ($expectedVersion !== $currentVersion) {
                    throw new MappingMutationException(
                        'MAPPING_VERSION_STALE',
                        'The mapping series changed since it was loaded. Refresh the history and try again.',
                        409,
                    );
                }

                $this->assertEvidenceOwnership($attributes['evidence_ids'] ?? [], $sourceConcept);

                $mapping = ConceptMapping::query()->create([
                    'mapping_rule_id' => 'MAP-'.Str::uuid()->toString(),
                    'mapping_series_key' => $mappingSeries,
                    'supersedes_mapping_rule_id' => $latest?->mapping_rule_id,
                    'source_concept' => $sourceConcept,
                    'entry_point' => $entryPoint,
                    'canonical_concept' => $attributes['canonical_concept'],
                    'allowed_scope' => $attributes['allowed_scope'],
                    'period_type' => $attributes['period_type'],
                    'sign_convention' => $attributes['sign_convention'],
                    'status' => $attributes['status'],
                    'rationale' => trim($attributes['rationale']),
                    'evidence_ids' => $attributes['evidence_ids'] ?? [],
                    'created_by' => 'system',
                    'rule_version' => ($currentVersion ?? 0) + 1,
                ]);

                AuditLog::query()->create([
                    'actor_id' => 'system',
                    'action' => 'concept_mapping.version_created',
                    'entity_type' => ConceptMapping::class,
                    'entity_id' => $mapping->mapping_rule_id,
                    'old_value' => $latest === null ? null : $this->auditValue($latest),
                    'new_value' => $this->auditValue($mapping),
                    'rationale' => $mapping->rationale,
                ]);

                return $mapping;
            });
        } catch (QueryException $exception) {
            if ($this->isVersionConflict($exception)) {
                throw new MappingMutationException(
                    'MAPPING_VERSION_CONFLICT',
                    'A concurrent mapping version was accepted. Refresh the history before trying again.',
                    409,
                );
            }

            throw $exception;
        }
    }

    /** @param array<int, string> $evidenceIds */
    private function assertEvidenceOwnership(array $evidenceIds, string $sourceConcept): void
    {
        if ($evidenceIds === []) {
            return;
        }

        $evidence = EvidenceRecord::query()->whereIn('evidence_id', $evidenceIds)->get(['evidence_id', 'source_concept']);
        if ($evidence->count() !== count(array_unique($evidenceIds))) {
            throw new MappingMutationException(
                'EVIDENCE_NOT_FOUND',
                'One or more evidence records do not exist.',
                422,
                'evidence_ids',
            );
        }

        if ($evidence->contains(fn (EvidenceRecord $record): bool => $record->source_concept !== null && $record->source_concept !== $sourceConcept)) {
            throw new MappingMutationException(
                'EVIDENCE_NOT_OWNED',
                'Every evidence record must belong to the mapping source concept.',
                422,
                'evidence_ids',
            );
        }
    }

    /** @return array<string, mixed> */
    private function auditValue(ConceptMapping $mapping): array
    {
        return [
            'mapping_rule_id' => (string) $mapping->mapping_rule_id,
            'mapping_series_key' => (string) $mapping->mapping_series_key,
            'supersedes_mapping_rule_id' => $mapping->supersedes_mapping_rule_id,
            'source_concept' => (string) $mapping->source_concept,
            'entry_point' => $mapping->entry_point,
            'canonical_concept' => (string) $mapping->canonical_concept,
            'allowed_scope' => $mapping->allowed_scope,
            'period_type' => $mapping->period_type,
            'sign_convention' => $mapping->sign_convention,
            'status' => (string) $mapping->status,
            'rationale' => $mapping->rationale,
            'evidence_ids' => $mapping->evidence_ids ?? [],
            'created_by' => $mapping->created_by,
            'rule_version' => (int) $mapping->rule_version,
        ];
    }

    private function isVersionConflict(QueryException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'concept_mappings_series_version_unique')
            || str_contains(strtolower($exception->getMessage()), 'unique constraint failed: concept_mappings.mapping_series_key');
    }
}
