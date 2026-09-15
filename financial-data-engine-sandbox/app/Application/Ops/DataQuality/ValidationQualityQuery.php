<?php

namespace App\Application\Ops\DataQuality;

use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\ValidationResult;
use App\Models\ValidationRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ValidationQualityQuery
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ValidationResult::query();
        $this->scopeExecution($query, $filters);

        return $query
            ->orderByDesc('checked_at')
            ->orderBy('validation_result_id')
            ->paginate((int) $filters['per_page'], ['*'], 'page', (int) $filters['page'])
            ->through(fn (ValidationResult $result): array => $this->item($result));
    }

    /** @param array<string, mixed> $filters */
    public function summary(array $filters): array
    {
        $results = ValidationResult::query()
            ->where('filing_id', $filters['filing_id'])
            ->where('normalized_dataset_version', $filters['dataset_version'])
            ->where('validation_rule_set_version', $filters['rule_set_version'])
            ->get(['result', 'severity']);
        $filing = Filing::query()->find($filters['filing_id']);
        $byResult = array_fill_keys(['PASS', 'FAIL', 'REVIEW_REQUIRED', 'SKIPPED'], 0);
        $bySeverity = array_fill_keys(['ERROR', 'WARN', 'INFO'], 0);
        foreach ($results as $result) {
            $byResult[$result->result] = ($byResult[$result->result] ?? 0) + 1;
            $bySeverity[$result->severity] = ($bySeverity[$result->severity] ?? 0) + 1;
        }
        $qualityStatus = $this->qualityStatus($filing, $results->pluck('result')->all());

        return [
            'filingId' => (string) $filters['filing_id'],
            'normalizedDatasetVersion' => (string) $filters['dataset_version'],
            'validationRuleSetVersion' => (string) $filters['rule_set_version'],
            'total' => $results->count(),
            'byResult' => $byResult,
            'bySeverity' => $bySeverity,
            'qualityStatus' => $qualityStatus,
            'verifiedInvariant' => $qualityStatus === QualityStatus::Verified->value,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function detail(string $validationResultId, array $filters): ?array
    {
        $query = ValidationResult::query()->whereKey($validationResultId);
        $this->scopeExecution($query, $filters);
        $result = $query->first();

        return $result === null ? null : $this->item($result, true);
    }

    private function scopeExecution(Builder $query, array $filters): void
    {
        $query
            ->where('filing_id', $filters['filing_id'])
            ->where('normalized_dataset_version', $filters['dataset_version'])
            ->where('validation_rule_set_version', $filters['rule_set_version']);
        foreach (['severity', 'result', 'rule_code'] as $field) {
            if (($value = $filters[$field] ?? null) !== null && $value !== '') {
                $query->where($field, $value);
            }
        }
    }

    /** @return array<string, mixed> */
    private function item(ValidationResult $result, bool $detail = false): array
    {
        $rule = ValidationRule::query()
            ->where('rule_code', $result->rule_code)
            ->where('rule_version', $result->rule_version)
            ->first();
        $factIds = array_values(array_filter((array) $result->normalized_fact_ids, 'is_string'));
        $facts = NormalizedFact::query()
            ->where('filing_id', $result->filing_id)
            ->whereIn('normalized_fact_id', $factIds)
            ->get(['normalized_fact_id', 'filing_id', 'canonical_concept', 'value', 'currency', 'source_concept']);
        $payload = [
            'validationResultId' => (string) $result->validation_result_id,
            'filingId' => (string) $result->filing_id,
            'normalizedDatasetVersion' => (string) $result->normalized_dataset_version,
            'validationRuleSetVersion' => (string) $result->validation_rule_set_version,
            'reviewVersion' => implode('|', [
                (string) $result->normalized_dataset_version,
                (string) $result->validation_rule_set_version,
                (string) $result->rule_code,
                (string) $result->rule_version,
            ]),
            'rule' => [
                'code' => (string) $result->rule_code,
                'version' => (int) $result->rule_version,
                'description' => $rule?->description,
                'severity' => $rule?->severity ?? $result->severity,
            ],
            'result' => (string) $result->result,
            'severity' => (string) $result->severity,
            'expectedValue' => $result->expected_value === null ? null : (string) $result->expected_value,
            'actualValue' => $result->actual_value === null ? null : (string) $result->actual_value,
            'tolerance' => $result->tolerance === null ? null : (string) $result->tolerance,
            'message' => $result->message,
            'checkedAt' => $result->checked_at?->toIso8601String(),
            'normalizedFactIds' => $factIds,
            'inputFacts' => $facts->map(static fn (NormalizedFact $fact): array => [
                'normalizedFactId' => (string) $fact->normalized_fact_id,
                'canonicalConcept' => $fact->canonical_concept,
                'value' => $fact->value === null ? null : (string) $fact->value,
                'currency' => $fact->currency,
                'sourceConcept' => $fact->source_concept,
                'href' => '/ops/financial-review?filing_id='.rawurlencode((string) $fact->filing_id).'&normalized_fact_id='.rawurlencode((string) $fact->normalized_fact_id),
            ])->values()->all(),
            'allowedActions' => [
                'markForReview' => ['allowed' => true, 'reasonCode' => null, 'reason' => null],
            ],
        ];

        if ($detail) {
            $payload['filing'] = Filing::query()->find($result->filing_id)?->only(['filing_id', 'issuer_code', 'fiscal_period', 'revision_number']);
        }

        return $payload;
    }

    /** @param list<string> $results */
    private function qualityStatus(?Filing $filing, array $results): string
    {
        if (in_array('FAIL', $results, true)) {
            return QualityStatus::Failed->value;
        }
        if (in_array('REVIEW_REQUIRED', $results, true)) {
            return QualityStatus::ReviewRequired->value;
        }

        return QualityStatus::tryFrom((string) $filing?->quality_status)?->value ?? QualityStatus::Pending->value;
    }
}
