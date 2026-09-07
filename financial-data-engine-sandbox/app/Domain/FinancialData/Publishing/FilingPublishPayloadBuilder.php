<?php

namespace App\Domain\FinancialData\Publishing;

use App\Domain\FinancialData\Publishing\Exceptions\InvalidPublishContractException;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\ValidationResult;

final class FilingPublishPayloadBuilder
{
    public function __construct(
        private readonly string $contract = 'hissa.financial-data.publish',
        private readonly string $contractVersion = '1.0.0',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Filing $filing): array
    {
        $latestValidation = ValidationResult::query()
            ->where('filing_id', $filing->filing_id)
            ->latest('id')
            ->first();

        if ($latestValidation === null || $latestValidation->normalized_dataset_version === null || $latestValidation->validation_rule_set_version === null) {
            throw new InvalidPublishContractException(['a compatible validation result is required']);
        }

        $validationResults = ValidationResult::query()
            ->where('filing_id', $filing->filing_id)
            ->where('normalized_dataset_version', $latestValidation->normalized_dataset_version)
            ->where('validation_rule_set_version', $latestValidation->validation_rule_set_version)
            ->orderBy('validation_result_id')
            ->get();

        $normalizedFactIds = $validationResults
            ->flatMap(fn (ValidationResult $result): array => array_values((array) $result->normalized_fact_ids))
            ->unique()
            ->sort()
            ->values();

        $normalizedFacts = NormalizedFact::query()
            ->where('filing_id', $filing->filing_id)
            ->whereIn('normalized_fact_id', $normalizedFactIds->all())
            ->orderBy('normalized_fact_id')
            ->get();

        $rawFactIds = $normalizedFacts->pluck('raw_fact_id')->filter()->unique()->sort()->values();
        $mappingVersions = $normalizedFacts->pluck('normalization_version')->filter()->unique()->sort()->values();

        if ($normalizedFacts->isEmpty() || $rawFactIds->isEmpty() || $mappingVersions->isEmpty() || $validationResults->isEmpty()) {
            throw new InvalidPublishContractException(['validated normalized lineage is incomplete']);
        }

        return [
            'contract' => $this->contract,
            'contract_version' => $this->contractVersion,
            'filing' => [
                'filing_id' => $filing->filing_id,
                'revision_number' => (int) $filing->revision_number,
                'issuer_code' => (string) $filing->issuer_code,
                'report_type' => (string) $filing->report_type,
                'fiscal_year' => (int) $filing->fiscal_year,
                'fiscal_period' => (string) $filing->fiscal_period,
                'period_end' => $filing->period_end?->format('Y-m-d'),
            ],
            'quality' => [
                'status' => (string) $filing->quality_status,
                'validation_rule_set_version' => (string) $latestValidation->validation_rule_set_version,
            ],
            'normalized_facts' => $normalizedFacts->map(fn (NormalizedFact $fact): array => [
                'normalized_fact_id' => (string) $fact->normalized_fact_id,
                'raw_fact_id' => (string) $fact->raw_fact_id,
                'canonical_concept' => (string) $fact->canonical_concept,
                'value' => (string) $fact->value,
                'currency' => $fact->currency,
                'scope' => $fact->scope,
                'period_end' => $fact->period_end?->format('Y-m-d'),
                'mapping_rule_id' => (string) $fact->mapping_rule_id,
                'mapping_rule_version' => (int) $fact->mapping_rule_version,
                'normalization_version' => (string) $fact->normalization_version,
            ])->values()->all(),
            'lineage' => [
                'raw_fact_ids' => $rawFactIds->all(),
                'normalized_fact_ids' => $normalizedFacts->pluck('normalized_fact_id')->values()->all(),
                'validation_result_ids' => $validationResults->pluck('validation_result_id')->values()->all(),
                'mapping_versions' => $mappingVersions->all(),
                'normalization_version' => (string) $mappingVersions->last(),
                'normalized_dataset_version' => (string) $latestValidation->normalized_dataset_version,
            ],
        ];
    }
}
