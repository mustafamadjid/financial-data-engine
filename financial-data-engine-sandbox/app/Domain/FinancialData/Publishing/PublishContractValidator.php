<?php

namespace App\Domain\FinancialData\Publishing;

use App\Domain\FinancialData\Publishing\Exceptions\InvalidPublishContractException;

final class PublishContractValidator
{
    public function __construct(
        private readonly string $expectedContract = 'hissa.financial-data.publish',
        private readonly string $expectedVersion = '1.0.0',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload): void
    {
        $violations = [];
        if (($payload['contract'] ?? null) !== $this->expectedContract) {
            $violations[] = 'contract identity is invalid';
        }

        if (($payload['contract_version'] ?? null) !== $this->expectedVersion) {
            $violations[] = 'contract version is invalid';
        }

        $filing = $payload['filing'] ?? null;
        if (! is_array($filing) || ! is_string($filing['filing_id'] ?? null) || trim($filing['filing_id']) === '' || ! is_int($filing['revision_number'] ?? null)) {
            $violations[] = 'filing identity is invalid';
        }

        $quality = $payload['quality'] ?? null;
        if (! is_array($quality) || ($quality['status'] ?? null) !== 'VERIFIED' || ! is_string($quality['validation_rule_set_version'] ?? null) || trim($quality['validation_rule_set_version']) === '') {
            $violations[] = 'quality status or validation version is invalid';
        }

        $normalizedFacts = $payload['normalized_facts'] ?? null;
        if (! is_array($normalizedFacts) || $normalizedFacts === []) {
            $violations[] = 'normalized facts are required';
        } else {
            $normalizedIds = [];
            $rawIds = [];

            foreach ($normalizedFacts as $index => $fact) {
                if (! is_array($fact)) {
                    $violations[] = "normalized fact [{$index}] is invalid";

                    continue;
                }

                if (! is_string($fact['normalized_fact_id'] ?? null) || trim($fact['normalized_fact_id']) === '') {
                    $violations[] = "normalized fact [{$index}] has no identity";
                } else {
                    $normalizedIds[] = $fact['normalized_fact_id'];
                }

                if (! is_string($fact['raw_fact_id'] ?? null) || trim($fact['raw_fact_id']) === '') {
                    $violations[] = "normalized fact [{$index}] has no raw lineage";
                } else {
                    $rawIds[] = $fact['raw_fact_id'];
                }

                if (! is_string($fact['value'] ?? null) || ! preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $fact['value'])) {
                    $violations[] = "normalized fact [{$index}] has an invalid decimal value";
                }
            }

            $lineage = $payload['lineage'] ?? null;
            if (! is_array($lineage)) {
                $violations[] = 'lineage is required';
            } else {
                foreach (['raw_fact_ids', 'normalized_fact_ids', 'validation_result_ids', 'mapping_versions'] as $key) {
                    if (! is_array($lineage[$key] ?? null) || $lineage[$key] === []) {
                        $violations[] = "lineage [{$key}] is required";
                    }
                }

                if (is_array($lineage['raw_fact_ids'] ?? null) && array_diff($rawIds, $lineage['raw_fact_ids']) !== []) {
                    $violations[] = 'lineage does not contain every raw fact';
                }

                if (is_array($lineage['normalized_fact_ids'] ?? null) && array_diff($normalizedIds, $lineage['normalized_fact_ids']) !== []) {
                    $violations[] = 'lineage does not contain every normalized fact';
                }

                foreach (['normalization_version', 'normalized_dataset_version'] as $key) {
                    if (! is_string($lineage[$key] ?? null) || trim($lineage[$key]) === '') {
                        $violations[] = "lineage [{$key}] is required";
                    }
                }
            }
        }

        if ($violations !== []) {
            throw new InvalidPublishContractException(array_values(array_unique($violations)));
        }
    }
}
