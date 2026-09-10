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
        if (! is_array($quality) || ($quality['status'] ?? null) !== 'VERIFIED' || ! is_string($quality['validation_rule_set_version'] ?? null) || trim($quality['validation_rule_set_version']) === '' || ! is_string($quality['normalized_dataset_version'] ?? null) || trim($quality['normalized_dataset_version']) === '') {
            $violations[] = 'quality status or validation version is invalid';
        }

        $source = $payload['source'] ?? null;
        if (! is_array($source) || ! is_string($source['source_url'] ?? null) || trim($source['source_url']) === '' || ! is_string($source['source_hash'] ?? null) || trim($source['source_hash']) === '') {
            $violations[] = 'source provenance is required';
        } elseif (filter_var($source['source_url'], FILTER_VALIDATE_URL) === false || in_array(parse_url($source['source_url'], PHP_URL_SCHEME), ['http', 'https'], true) === false) {
            $violations[] = 'source url is invalid';
        }
        if (is_array($source) && isset($source['storage_reference']) && $source['storage_reference'] !== null && ! is_string($source['storage_reference'])) {
            $violations[] = 'storage reference is invalid';
        } elseif (is_array($source) && is_string($source['storage_reference'] ?? null) && preg_match('/\A(?:[A-Za-z]:[\\\\\\/]|[\\\\\\/]|https?:\\/\\/)/', $source['storage_reference']) === 1) {
            $violations[] = 'storage reference must be logical';
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

                if (($fact['canonical_concept'] ?? null) === 'UNMAPPED') {
                    $violations[] = "normalized fact [{$index}] cannot be UNMAPPED";
                }
            }

            if (count($normalizedIds) !== count(array_unique($normalizedIds))) {
                $violations[] = 'normalized fact identifiers are duplicated';
            }
            if (count($rawIds) !== count(array_unique($rawIds))) {
                $violations[] = 'raw fact identifiers are duplicated';
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

        $limitations = $payload['limitations'] ?? null;
        if (! is_array($limitations) || ! is_array($limitations['items'] ?? null) || ! is_array($limitations['unmapped_concepts'] ?? null)) {
            $violations[] = 'limitations are required';
        } else {
            foreach ($limitations['items'] as $index => $item) {
                if (! is_array($item) || ! is_string($item['code'] ?? null) || ! in_array($item['severity'] ?? null, ['INFO', 'WARN'], true) || ! is_string($item['message'] ?? null) || ! is_array($item['references'] ?? null)) {
                    $violations[] = "limitation [{$index}] is malformed";
                }
            }
            foreach ($limitations['unmapped_concepts'] as $index => $item) {
                if (! is_array($item) || ! is_string($item['source_concept'] ?? null) || trim($item['source_concept']) === '' || ! array_key_exists('source_reference', $item) || ! is_string($item['reason'] ?? null) || trim($item['reason']) === '') {
                    $violations[] = "unmapped limitation [{$index}] is malformed";
                }
            }
        }

        if ($violations !== []) {
            throw new InvalidPublishContractException(array_values(array_unique($violations)));
        }
    }
}
