<?php

namespace App\Domain\FinancialData\Publishing;

use App\Models\Filing;
use App\Models\ValidationResult;

final class PublishLimitationsCollector
{
    /**
     * @return array{items: list<array<string, mixed>>, unmapped_concepts: list<array<string, mixed>>}
     */
    public function collect(Filing $filing, string $normalizedDatasetVersion, string $validationRuleSetVersion): array
    {
        $items = ValidationResult::query()
            ->where('filing_id', $filing->filing_id)
            ->where('normalized_dataset_version', $normalizedDatasetVersion)
            ->where('validation_rule_set_version', $validationRuleSetVersion)
            ->whereIn('severity', ['INFO', 'WARN'])
            ->where('result', '!=', 'PASS')
            ->orderBy('validation_result_id')
            ->get()
            ->map(function (ValidationResult $result): array {
                $references = array_values(array_unique(array_merge(
                    [(string) $result->validation_result_id],
                    array_values((array) $result->normalized_fact_ids),
                )));
                sort($references);

                return [
                    'code' => 'VALIDATION_'.(string) $result->rule_code,
                    'severity' => (string) $result->severity,
                    'message' => (string) ($result->message ?: 'Validation disclosure was recorded.'),
                    'references' => $references,
                ];
            })
            ->values()
            ->all();

        $unmappedConcepts = $filing->auditLogs()
            ->where('action', 'normalization.review_required')
            ->orderBy('id')
            ->get()
            ->filter(function ($auditLog) use ($normalizedDatasetVersion): bool {
                return data_get($auditLog->new_value, 'normalization_version') === $normalizedDatasetVersion;
            })
            ->map(function ($auditLog): array {
                $newValue = (array) $auditLog->new_value;

                return [
                    'source_concept' => (string) ($newValue['source_concept'] ?? $auditLog->entity_id),
                    'source_reference' => $newValue['source_reference'] ?? $auditLog->entity_id,
                    'reason' => (string) ($newValue['reason'] ?? $auditLog->rationale ?: 'Normalization review is required.'),
                ];
            })
            ->values()
            ->all();

        usort($unmappedConcepts, fn (array $left, array $right): int => [$left['source_concept'], $left['source_reference']] <=> [$right['source_concept'], $right['source_reference']]);

        return [
            'items' => $items,
            'unmapped_concepts' => array_values(array_unique($unmappedConcepts, SORT_REGULAR)),
        ];
    }
}
