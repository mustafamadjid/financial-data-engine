<?php

namespace App\Application\Ops\FinancialReview;

use App\Models\NormalizedFact;
use App\Models\ValidationResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class FinancialFactQuery
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = NormalizedFact::query()
            ->with([
                'filing',
                'rawFact.context',
                'rawFact.unit',
                'mappingRule.canonicalConcept',
            ]);

        $this->applyFilters($query, $filters);
        $this->applyOrdering($query, (string) ($filters['sort'] ?? 'filing_asc'));

        return $query->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function detail(NormalizedFact $fact): array
    {
        $fact->loadMissing([
            'filing',
            'rawFact.context',
            'rawFact.unit',
            'mappingRule.canonicalConcept',
        ]);

        $validationResults = ValidationResult::query()
            ->where('filing_id', $fact->filing_id)
            ->whereJsonContains('normalized_fact_ids', $fact->normalized_fact_id)
            ->orderByDesc('checked_at')
            ->orderBy('validation_result_id')
            ->get();

        return $this->item($fact) + [
            'validationResults' => $validationResults->map(static fn (ValidationResult $result): array => [
                'validationResultId' => $result->validation_result_id,
                'ruleCode' => $result->rule_code,
                'ruleVersion' => $result->rule_version,
                'result' => $result->result,
                'severity' => $result->severity,
                'message' => $result->message,
                'expectedValue' => $result->expected_value === null ? null : (string) $result->expected_value,
                'actualValue' => $result->actual_value === null ? null : (string) $result->actual_value,
                'tolerance' => $result->tolerance === null ? null : (string) $result->tolerance,
                'checkedAt' => $result->checked_at?->toIso8601String(),
                'normalizedDatasetVersion' => $result->normalized_dataset_version,
                'validationRuleSetVersion' => $result->validation_rule_set_version,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function item(NormalizedFact $fact): array
    {
        $rawFact = $fact->rawFact;
        $context = $rawFact?->context;
        $unit = $rawFact?->unit;
        $filing = $fact->filing;
        $mapping = $fact->mappingRule;

        return [
            'normalizedFactId' => $fact->normalized_fact_id,
            'filing' => $filing === null ? null : [
                'filingId' => $fact->filing_id,
                'issuerCode' => $filing->issuer_code,
                'fiscalPeriod' => $filing->fiscal_period,
                'revisionNumber' => $filing->revision_number,
            ],
            'canonicalConcept' => $fact->canonicalConcept === null ? null : [
                'code' => $fact->canonicalConcept->code,
                'name' => $fact->canonicalConcept->name,
            ],
            'value' => $fact->value === null ? null : (string) $fact->value,
            'currency' => $fact->currency,
            'period' => $fact->period,
            'periodStart' => $fact->period_start?->toDateString(),
            'periodEnd' => $fact->period_end?->toDateString(),
            'scope' => $fact->scope,
            'dataType' => $fact->data_type,
            'normalizationStatus' => $fact->normalization_status,
            'validationStatus' => $fact->validation_status,
            'sourceConcept' => $fact->source_concept,
            'normalizationVersion' => $fact->normalization_version,
            'mapping' => $mapping === null ? null : [
                'mappingRuleId' => $mapping->mapping_rule_id,
                'mappingSeriesKey' => $mapping->mapping_series_key,
                'entryPoint' => $mapping->entry_point,
                'canonicalConcept' => $mapping->canonical_concept,
                'status' => $mapping->status,
                'ruleVersion' => $mapping->rule_version,
                'rationale' => $mapping->rationale,
            ],
            'rawFact' => $rawFact === null ? null : [
                'rawFactId' => $rawFact->raw_fact_id,
                'sourceConcept' => $rawFact->source_concept,
                'sourceNamespace' => $rawFact->source_namespace,
                'rawValue' => $rawFact->raw_value,
                'normalizedNumericValue' => $rawFact->normalized_numeric_value === null ? null : (string) $rawFact->normalized_numeric_value,
                'isNil' => $rawFact->is_nil,
                'factStatus' => $rawFact->fact_status,
                'context' => $context === null ? null : [
                    'contextId' => $context->context_id,
                    'entityIdentifier' => $context->entity_identifier,
                    'scope' => $context->scope,
                    'periodType' => $context->period_type,
                    'instantDate' => $context->instant_date?->toDateString(),
                    'startDate' => $context->start_date?->toDateString(),
                    'endDate' => $context->end_date?->toDateString(),
                ],
                'unit' => $unit === null ? null : [
                    'unitId' => $unit->unit_id,
                    'unitType' => $unit->unit_type,
                    'measure' => $unit->measure,
                    'currency' => $unit->currency,
                ],
            ],
            'allowedActions' => [
                'openEvidence' => ['allowed' => $filing?->storage_path !== null, 'reasonCode' => $filing?->storage_path === null ? 'EVIDENCE_NOT_AVAILABLE' : null, 'reason' => null],
                'openMapping' => ['allowed' => $mapping !== null, 'reasonCode' => $mapping === null ? 'MAPPING_NOT_AVAILABLE' : null, 'reason' => null],
                'markForReview' => ['allowed' => true, 'reasonCode' => null, 'reason' => null],
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (($filingId = $filters['filing_id'] ?? null) !== null && $filingId !== '') {
            $query->where('normalized_facts.filing_id', $filingId);
        }
        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $query->where(static function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('normalized_facts.normalized_fact_id', 'like', "%{$search}%")
                    ->orWhere('normalized_facts.source_concept', 'like', "%{$search}%")
                    ->orWhere('normalized_facts.canonical_concept', 'like', "%{$search}%")
                    ->orWhere('normalized_facts.issuer_code', 'like', "%{$search}%");
            });
        }
        foreach (['canonical_concept', 'scope', 'normalization_status', 'validation_status'] as $field) {
            if (($value = $filters[$field] ?? null) !== null && $value !== '') {
                $query->where('normalized_facts.'.$field, $value);
            }
        }
    }

    private function applyOrdering(Builder $query, string $sort): void
    {
        match ($sort) {
            'concept_asc' => $query->orderBy('normalized_facts.canonical_concept')->orderBy('normalized_facts.normalized_fact_id'),
            'period_desc' => $query->orderByDesc('normalized_facts.period_end')->orderBy('normalized_facts.normalized_fact_id'),
            default => $query->orderBy('normalized_facts.filing_id')->orderBy('normalized_facts.normalized_fact_id'),
        };
    }
}
