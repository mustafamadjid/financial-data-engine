<?php

namespace App\Domain\FinancialData\Pipeline;

use InvalidArgumentException;

final readonly class PipelineIdempotencyKey
{
    private function __construct(private string $canonicalPayload) {}

    public static function discovery(string $sourceAdapter, string $discoveryWindow, string $stableCandidateIdentity): self
    {
        return self::forStage('discovery', [
            'source_adapter' => $sourceAdapter,
            'discovery_window' => $discoveryWindow,
            'stable_candidate_identity' => $stableCandidateIdentity,
        ]);
    }

    public static function download(string $filingId, string $sourceLocator, string $revisionIdentifier): self
    {
        return self::forStage('download', [
            'filing_id' => $filingId,
            'source_locator' => $sourceLocator,
            'revision_identifier' => $revisionIdentifier,
        ]);
    }

    public static function parse(string $filingId, string $sourceHash, string $parserVersion, string $parserConfigVersion): self
    {
        return self::forStage('parse', [
            'filing_id' => $filingId,
            'source_hash' => $sourceHash,
            'parser_version' => $parserVersion,
            'parser_config_version' => $parserConfigVersion,
        ]);
    }

    public static function normalize(string $filingId, string $rawExtractionVersion, string $mappingVersion, string $normalizationVersion): self
    {
        return self::forStage('normalize', [
            'filing_id' => $filingId,
            'raw_extraction_version' => $rawExtractionVersion,
            'mapping_version' => $mappingVersion,
            'normalization_version' => $normalizationVersion,
        ]);
    }

    public static function validate(string $filingId, string $normalizedDatasetVersion, string $validationRuleSetVersion): self
    {
        return self::forStage('validate', [
            'filing_id' => $filingId,
            'normalized_dataset_version' => $normalizedDatasetVersion,
            'validation_rule_set_version' => $validationRuleSetVersion,
        ]);
    }

    public static function publish(string $filingId, string $validationRunId, string $publishContractVersion): self
    {
        return self::forStage('publish', [
            'filing_id' => $filingId,
            'validation_run_id' => $validationRunId,
            'publish_contract_version' => $publishContractVersion,
        ]);
    }

    public static function forStage(string $stage, array $inputs): self
    {
        $stage = strtolower(trim($stage));
        $requiredInputs = self::requiredInputs()[$stage] ?? null;

        if ($requiredInputs === null) {
            throw new InvalidArgumentException("Unsupported pipeline stage [{$stage}].");
        }

        foreach ($requiredInputs as $input) {
            if (! array_key_exists($input, $inputs) || ! is_scalar($inputs[$input]) || (string) $inputs[$input] === '') {
                throw new InvalidArgumentException("Missing required idempotency input [{$input}] for stage [{$stage}].");
            }
        }

        $canonicalPayload = $stage;

        foreach ($requiredInputs as $input) {
            $canonicalPayload .= '|'.$input.'='.self::canonicalize((string) $inputs[$input]);
        }

        return new self($canonicalPayload);
    }

    public function value(): string
    {
        return hash('sha256', $this->canonicalPayload);
    }

    public function canonicalPayload(): string
    {
        return $this->canonicalPayload;
    }

    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * @return array<string, list<string>>
     */
    private static function requiredInputs(): array
    {
        return [
            'discovery' => ['source_adapter', 'discovery_window', 'stable_candidate_identity'],
            'download' => ['filing_id', 'source_locator', 'revision_identifier'],
            'parse' => ['filing_id', 'source_hash', 'parser_version', 'parser_config_version'],
            'normalize' => ['filing_id', 'raw_extraction_version', 'mapping_version', 'normalization_version'],
            'validate' => ['filing_id', 'normalized_dataset_version', 'validation_rule_set_version'],
            'publish' => ['filing_id', 'validation_run_id', 'publish_contract_version'],
        ];
    }

    private static function canonicalize(string $value): string
    {
        return str_replace(['%', '|', '='], ['%25', '%7C', '%3D'], $value);
    }
}
