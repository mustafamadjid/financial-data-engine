<?php

namespace Tests\Support;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final readonly class AcceptedPublishedFiling
{
    /** @param array<string, mixed> $document */
    public function __construct(public array $document) {}

    public function snapshotId(): string
    {
        return (string) $this->document['snapshot']['snapshot_id'];
    }
}

final class HissaCoreMockClient
{
    public function acceptJson(string $json): AcceptedPublishedFiling
    {
        return $this->accept(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $document */
    public function accept(array $document): AcceptedPublishedFiling
    {
        if (($document['api_version'] ?? null) !== 'v1' || ($document['contract'] ?? null) !== 'hissa.financial-data.integration' || ($document['contract_version'] ?? null) !== '0.1.0') {
            throw new InvalidArgumentException('Unsupported integration contract.');
        }
        if (data_get($document, 'quality.status') !== 'VERIFIED') {
            throw new InvalidArgumentException('Published document is not verified.');
        }
        foreach (['source_url', 'source_hash', 'pipeline_run_id', 'raw_fact_ids', 'normalized_fact_ids', 'validation_result_ids', 'mapping_versions'] as $key) {
            if (! array_key_exists($key, (array) ($document['provenance'] ?? []))) {
                throw new InvalidArgumentException('Published document is missing provenance.');
            }
        }
        foreach ((array) ($document['facts'] ?? []) as $fact) {
            if (! is_string($fact['value'] ?? null) || ($fact['canonical_concept'] ?? null) === 'UNMAPPED') {
                throw new InvalidArgumentException('Published fact is not consumable.');
            }
        }

        return new AcceptedPublishedFiling($document);
    }

    public function acceptResponse(Response $response): AcceptedPublishedFiling
    {
        return $this->acceptJson((string) $response->getContent());
    }
}
