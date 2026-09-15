<?php

namespace App\Domain\FinancialData\Parsing;

final readonly class ParsedFilingData
{
    /**
     * @param  array<string, mixed>  $runtime
     * @param  list<array<string, mixed>>  $contexts
     * @param  list<array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $dimensions
     * @param  list<array<string, mixed>>  $facts
     * @param  list<array<string, mixed>>  $warnings
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        public string $filingId,
        public string $sourceHash,
        public string $parserVersion,
        public string $parserConfigVersion,
        public array $runtime,
        public array $contexts,
        public array $units,
        public array $dimensions,
        public array $facts,
        public array $warnings = [],
        public array $errors = [],
        public ?string $taxonomyEntryPoint = null,
    ) {}

    public function extractionIdentity(): string
    {
        return hash('sha256', implode('|', [
            $this->filingId,
            $this->sourceHash,
            $this->parserVersion,
            $this->parserConfigVersion,
        ]));
    }
}
