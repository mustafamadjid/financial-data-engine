<?php

namespace App\Domain\FinancialData\Parsing;

use App\Domain\FinancialData\Parsing\Exceptions\TerminalParserException;

final class ParserOutputValidator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload, ParserExecutionContext $context): ParsedFilingData
    {
        $this->requiredString($payload, 'parser_contract');
        $this->requiredString($payload, 'parser_contract_version');
        $this->requiredString($payload, 'status');
        $this->requiredString($payload, 'filing_id');

        if ($payload['parser_contract'] !== 'xbrl_parser_result' || $payload['parser_contract_version'] !== $context->contractVersion) {
            throw new TerminalParserException('Parser contract version is unsupported.');
        }

        if ($payload['status'] !== 'SUCCESS' || $payload['filing_id'] !== $context->filingId) {
            throw new TerminalParserException('Parser output identity or status is invalid.');
        }

        $source = $this->object($payload, 'source');
        $sourceHash = $this->requiredString($source, 'sha256');

        if (! hash_equals($context->sourceHash, $sourceHash)) {
            throw new TerminalParserException('Parser source hash does not match the filing artifact.');
        }

        $runtime = $this->object($payload, 'runtime');
        $parserVersion = $this->requiredString($runtime, 'worker_version');
        $this->requiredString($runtime, 'python_version');
        $this->requiredString($runtime, 'arelle_version');

        if ($parserVersion !== $context->parserVersion) {
            throw new TerminalParserException('Parser runtime version does not match the configured version.');
        }

        $counts = $this->object($payload, 'counts');
        $contexts = $this->records($payload, 'contexts');
        $units = $this->records($payload, 'units');
        $dimensions = $this->records($payload, 'dimensions');
        $facts = $this->records($payload, 'facts');
        $warnings = $this->records($payload, 'warnings');
        $errors = $this->records($payload, 'errors');

        foreach ([
            'contexts' => $contexts,
            'units' => $units,
            'dimensions' => $dimensions,
            'facts' => $facts,
        ] as $name => $records) {
            if (($counts[$name] ?? null) !== count($records)) {
                throw new TerminalParserException("Parser count for {$name} is invalid.");
            }
        }

        $contextIds = $this->validateContexts($contexts, $context->filingId);
        $unitIds = $this->validateUnits($units, $context->filingId);
        $this->validateDimensions($dimensions, $contextIds);
        $this->validateFacts($facts, $context->filingId, $contextIds, $unitIds);

        return new ParsedFilingData(
            filingId: $context->filingId,
            sourceHash: $sourceHash,
            parserVersion: $parserVersion,
            parserConfigVersion: $context->parserConfigVersion,
            runtime: $runtime,
            contexts: $contexts,
            units: $units,
            dimensions: $dimensions,
            facts: $facts,
            warnings: $warnings,
            errors: $errors,
        );
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        if (! isset($payload[$key]) || ! is_string($payload[$key]) || trim($payload[$key]) === '') {
            throw new TerminalParserException("Parser field [{$key}] is invalid.");
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function object(array $payload, string $key): array
    {
        if (! isset($payload[$key]) || ! is_array($payload[$key])) {
            throw new TerminalParserException("Parser object [{$key}] is invalid.");
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload @return list<array<string, mixed>> */
    private function records(array $payload, string $key): array
    {
        $records = $this->object($payload, $key);

        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new TerminalParserException("Parser collection [{$key}] contains an invalid record.");
            }
        }

        return array_values($records);
    }

    /** @param list<array<string, mixed>> $records @return array<string, true> */
    private function validateContexts(array $records, string $filingId): array
    {
        $ids = [];

        foreach ($records as $record) {
            foreach (['context_id', 'filing_id', 'entity_identifier', 'period_type'] as $key) {
                $this->requiredString($record, $key);
            }

            if ($record['filing_id'] !== $filingId || ! in_array($record['period_type'], ['INSTANT', 'DURATION', 'FOREVER'], true) || ! in_array($record['context_status'] ?? null, ['RESOLVED', 'REVIEW_REQUIRED', 'INVALID'], true)) {
                throw new TerminalParserException('Parser context semantics are invalid.');
            }

            $ids[$record['context_id']] = true;
        }

        return $ids;
    }

    /** @param list<array<string, mixed>> $records @return array<string, true> */
    private function validateUnits(array $records, string $filingId): array
    {
        $ids = [];

        foreach ($records as $record) {
            foreach (['unit_id', 'filing_id', 'source_unit_id', 'unit_type'] as $key) {
                $this->requiredString($record, $key);
            }

            if ($record['filing_id'] !== $filingId || ! in_array($record['unit_type'], ['CURRENCY', 'SHARES', 'RATIO', 'PURE', 'CUSTOM', 'UNKNOWN'], true)) {
                throw new TerminalParserException('Parser unit semantics are invalid.');
            }

            $ids[$record['unit_id']] = true;
        }

        return $ids;
    }

    /** @param list<array<string, mixed>> $records @param array<string, true> $contextIds */
    private function validateDimensions(array $records, array $contextIds): void
    {
        foreach ($records as $record) {
            foreach (['dimension_id', 'context_id', 'axis'] as $key) {
                $this->requiredString($record, $key);
            }

            if (! isset($contextIds[$record['context_id']])) {
                throw new TerminalParserException('Parser dimension references an unknown context.');
            }
        }
    }

    /** @param list<array<string, mixed>> $records @param array<string, true> $contextIds @param array<string, true> $unitIds */
    private function validateFacts(array $records, string $filingId, array $contextIds, array $unitIds): void
    {
        foreach ($records as $record) {
            foreach (['raw_fact_id', 'filing_id', 'source_concept', 'context_ref', 'raw_value', 'fact_status'] as $key) {
                $this->requiredString($record, $key);
            }

            if ($record['filing_id'] !== $filingId || ! isset($contextIds[$record['context_ref']]) || ($record['unit_ref'] !== null && ! isset($unitIds[$record['unit_ref']])) || ! is_bool($record['is_nil'] ?? null) || ! in_array($record['fact_status'], ['EXTRACTED', 'UNSUPPORTED', 'PARSE_ERROR'], true)) {
                throw new TerminalParserException('Parser fact semantics are invalid.');
            }

            if (isset($record['normalized_numeric_value']) && $record['normalized_numeric_value'] !== null && ! is_string($record['normalized_numeric_value'])) {
                throw new TerminalParserException('Parser numeric value representation is invalid.');
            }
        }
    }
}
