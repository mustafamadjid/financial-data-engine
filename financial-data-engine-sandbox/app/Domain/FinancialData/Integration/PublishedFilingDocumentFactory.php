<?php

namespace App\Domain\FinancialData\Integration;

use App\Domain\FinancialData\Integration\Exceptions\IntegrationContractViolation;
use App\Domain\FinancialData\Publishing\Exceptions\InvalidPublishContractException;
use App\Domain\FinancialData\Publishing\PublishContractValidator;
use App\Models\PublishedSnapshot;
use Carbon\Carbon;

final class PublishedFilingDocumentFactory
{
    public function __construct(
        private readonly ?PublishContractValidator $publishContractValidator = null,
        private readonly string $contractVersion = '0.1.0',
    ) {}

    public function make(PublishedSnapshot $snapshot): PublishedFilingDocument
    {
        $payload = (array) $snapshot->payload;
        $this->validateSnapshot($snapshot, $payload);

        try {
            ($this->publishContractValidator ?? new PublishContractValidator)->validate($this->internalPayload($payload));
        } catch (InvalidPublishContractException) {
            throw new IntegrationContractViolation('MALFORMED_SNAPSHOT');
        }

        $filing = $payload['filing'];
        $quality = $payload['quality'];
        $source = $payload['source'];
        $lineage = array_merge((array) ($payload['lineage'] ?? []), (array) $snapshot->lineage);
        $facts = array_map(fn (array $fact): array => $this->mapFact($fact), $payload['normalized_facts']);

        usort($facts, fn (array $left, array $right): int => [
            (string) $left['canonical_concept'],
            (string) ($left['period_end'] ?? ''),
            (string) ($left['scope'] ?? ''),
            (string) $left['normalized_fact_id'],
        ] <=> [
            (string) $right['canonical_concept'],
            (string) ($right['period_end'] ?? ''),
            (string) ($right['scope'] ?? ''),
            (string) $right['normalized_fact_id'],
        ]);

        $publishedAt = $this->timestamp($snapshot->published_at);

        return new PublishedFilingDocument([
            'api_version' => 'v1',
            'contract' => 'hissa.financial-data.integration',
            'contract_version' => $this->contractVersion,
            'snapshot' => [
                'snapshot_id' => (string) $snapshot->snapshot_id,
                'published_at' => $publishedAt,
            ],
            'filing' => [
                'filing_id' => (string) $filing['filing_id'],
                'issuer_code' => (string) $filing['issuer_code'],
                'report_type' => (string) $filing['report_type'],
                'fiscal_year' => (int) $filing['fiscal_year'],
                'fiscal_period' => (string) $filing['fiscal_period'],
                'period_start' => $filing['period_start'] ?? null,
                'period_end' => $filing['period_end'],
                'revision_number' => (int) $filing['revision_number'],
                'supersedes_filing_id' => $filing['supersedes_filing_id'] ?? null,
            ],
            'quality' => [
                'status' => 'VERIFIED',
                'validation_rule_set_version' => (string) $quality['validation_rule_set_version'],
                'normalized_dataset_version' => (string) $quality['normalized_dataset_version'],
                'validation_result_ids' => $this->sortedIds($quality['validation_result_ids'] ?? $lineage['validation_result_ids'] ?? []),
            ],
            'facts' => $facts,
            'provenance' => [
                'source_url' => (string) $source['source_url'],
                'source_hash' => (string) $source['source_hash'],
                'storage_reference' => $source['storage_reference'] ?? null,
                'raw_fact_ids' => $this->sortedIds($lineage['raw_fact_ids'] ?? []),
                'normalized_fact_ids' => $this->sortedIds($lineage['normalized_fact_ids'] ?? []),
                'validation_result_ids' => $this->sortedIds($lineage['validation_result_ids'] ?? []),
                'mapping_versions' => $this->sortedIds($lineage['mapping_versions'] ?? []),
                'normalization_version' => (string) $lineage['normalization_version'],
                'pipeline_run_id' => (int) ($lineage['pipeline_run_id'] ?? 0),
                'published_at' => $publishedAt,
            ],
            'limitations' => [
                'items' => array_values($payload['limitations']['items']),
                'unmapped_concepts' => array_values($payload['limitations']['unmapped_concepts']),
            ],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function validateSnapshot(PublishedSnapshot $snapshot, array $payload): void
    {
        if (! is_string($snapshot->snapshot_id) || trim($snapshot->snapshot_id) === '') {
            throw new IntegrationContractViolation('MISSING_SNAPSHOT_ID');
        }
        if (($payload['quality']['status'] ?? null) !== 'VERIFIED') {
            throw new IntegrationContractViolation('NON_VERIFIED_SNAPSHOT');
        }
        if (! is_array($payload['source'] ?? null) || ! is_string($payload['source']['source_url'] ?? null) || ! is_string($payload['source']['source_hash'] ?? null)) {
            throw new IntegrationContractViolation('MISSING_SOURCE_PROVENANCE');
        }
        if (is_string($payload['source']['storage_reference'] ?? null) && preg_match('/\A(?:[A-Za-z]:[\\\\\\/]|[\\\\\\/]|https?:\\/\\/)/', $payload['source']['storage_reference']) === 1) {
            throw new IntegrationContractViolation('UNSAFE_STORAGE_REFERENCE');
        }
        foreach ((array) ($payload['normalized_facts'] ?? []) as $fact) {
            if (! is_string($fact['value'] ?? null) || preg_match('/\A-?(?:0|[1-9]\d*)(?:\.\d+)?\z/', $fact['value']) !== 1) {
                throw new IntegrationContractViolation('INVALID_DECIMAL_STRING');
            }
        }
        foreach (['raw_fact_ids', 'normalized_fact_ids', 'validation_result_ids', 'mapping_versions'] as $key) {
            if (! is_array($payload['lineage'][$key] ?? null) || $payload['lineage'][$key] === []) {
                throw new IntegrationContractViolation('MISSING_FACT_LINEAGE');
            }
        }
        if (! is_array($payload['limitations']['items'] ?? null) || ! is_array($payload['limitations']['unmapped_concepts'] ?? null)) {
            throw new IntegrationContractViolation('INVALID_LIMITATION');
        }
        foreach ($payload['limitations']['items'] as $item) {
            if (! is_array($item) || ! is_string($item['code'] ?? null) || ! in_array($item['severity'] ?? null, ['INFO', 'WARN'], true) || ! is_string($item['message'] ?? null) || ! is_array($item['references'] ?? null)) {
                throw new IntegrationContractViolation('INVALID_LIMITATION');
            }
        }
        foreach ($payload['limitations']['unmapped_concepts'] as $item) {
            if (! is_array($item) || ! is_string($item['source_concept'] ?? null) || ! array_key_exists('source_reference', $item) || ! is_string($item['reason'] ?? null)) {
                throw new IntegrationContractViolation('INVALID_LIMITATION');
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function internalPayload(array $payload): array
    {
        return $payload;
    }

    /** @param array<string, mixed> $fact */
    private function mapFact(array $fact): array
    {
        if (($fact['canonical_concept'] ?? null) === 'UNMAPPED') {
            throw new IntegrationContractViolation('UNMAPPED_CANONICAL_FACT');
        }

        return [
            'normalized_fact_id' => (string) $fact['normalized_fact_id'],
            'raw_fact_id' => (string) $fact['raw_fact_id'],
            'canonical_concept' => (string) $fact['canonical_concept'],
            'value' => (string) $fact['value'],
            'currency' => $fact['currency'] ?? null,
            'scope' => $fact['scope'] ?? null,
            'period_start' => $fact['period_start'] ?? null,
            'period_end' => $fact['period_end'] ?? null,
            'data_type' => (string) $fact['data_type'],
            'mapping_rule_id' => (string) $fact['mapping_rule_id'],
            'mapping_rule_version' => (int) $fact['mapping_rule_version'],
            'normalization_version' => (string) $fact['normalization_version'],
            'validation_status' => (string) $fact['validation_status'],
        ];
    }

    /** @param array<int, mixed> $values @return list<string> */
    private function sortedIds(array $values): array
    {
        $values = array_values(array_unique(array_map(static fn ($value): string => (string) $value, $values)));
        sort($values);

        return $values;
    }

    private function timestamp(mixed $value): string
    {
        return Carbon::parse($value)->toIso8601String();
    }
}
