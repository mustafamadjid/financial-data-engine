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
        $this->validateWarnings($warnings);
        $taxonomyEntryPoint = $this->optionalString($payload, 'taxonomy_entry_point');
        $taxonomy = [];
        if ($context->contractVersion === '2.0.0') {
            $taxonomy = $this->object($payload, 'taxonomy');
            foreach (['imports', 'import_locations', 'linkbase_roles', 'linkbase_references', 'statement_families'] as $key) {
                if (! isset($taxonomy[$key]) || ! is_array($taxonomy[$key])) {
                    throw new TerminalParserException('Parser taxonomy metadata is invalid.');
                }
            }
            $this->optionalString($taxonomy, 'target_namespace');
        }

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
        $this->validateFacts($facts, $context->filingId, $contextIds, $unitIds, $context->contractVersion === '2.0.0');

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
            taxonomyEntryPoint: $taxonomyEntryPoint,
            taxonomy: $taxonomy,
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

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (! is_string($payload[$key]) || trim($payload[$key]) === '' || strlen($payload[$key]) > 255) {
            throw new TerminalParserException("Parser field [{$key}] is invalid.");
        }

        return trim($payload[$key]);
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

            if (isset($ids[$record['context_id']])) {
                throw new TerminalParserException('Parser contains duplicate context identifiers.');
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

            if (isset($ids[$record['unit_id']])) {
                throw new TerminalParserException('Parser contains duplicate unit identifiers.');
            }
            $ids[$record['unit_id']] = true;
        }

        return $ids;
    }

    /** @param list<array<string, mixed>> $records @param array<string, true> $contextIds */
    private function validateDimensions(array $records, array $contextIds): void
    {
        $ids = [];

        foreach ($records as $record) {
            foreach (['dimension_id', 'context_id', 'axis'] as $key) {
                $this->requiredString($record, $key);
            }

            if (! isset($contextIds[$record['context_id']])) {
                throw new TerminalParserException('Parser dimension references an unknown context.');
            }

            if (isset($ids[$record['dimension_id']])) {
                throw new TerminalParserException('Parser contains duplicate dimension identifiers.');
            }
            $ids[$record['dimension_id']] = true;
        }
    }

    /** @param list<array<string, mixed>> $records @param array<string, true> $contextIds @param array<string, true> $unitIds */
    private function validateFacts(array $records, string $filingId, array $contextIds, array $unitIds, bool $v2): void
    {
        $ids = [];

        foreach ($records as $record) {
            foreach (['raw_fact_id', 'filing_id', 'source_concept', 'context_ref', 'raw_value', 'fact_status'] as $key) {
                $this->requiredString($record, $key);
            }
            if ($v2) {
                $this->requiredString($record, 'source_namespace');
                $this->requiredString($record, 'source_element_id');
            }

            if ($record['filing_id'] !== $filingId || ! isset($contextIds[$record['context_ref']]) || ($record['unit_ref'] !== null && ! isset($unitIds[$record['unit_ref']])) || ! is_bool($record['is_nil'] ?? null) || ! in_array($record['fact_status'], ['EXTRACTED', 'UNSUPPORTED', 'PARSE_ERROR'], true)) {
                throw new TerminalParserException('Parser fact semantics are invalid.');
            }

            if (isset($ids[$record['raw_fact_id']])) {
                throw new TerminalParserException('Parser contains duplicate fact identifiers.');
            }
            $ids[$record['raw_fact_id']] = true;

            if (isset($record['normalized_numeric_value']) && $record['normalized_numeric_value'] !== null && ! is_string($record['normalized_numeric_value'])) {
                throw new TerminalParserException('Parser numeric value representation is invalid.');
            }
        }
    }

    /** @param list<array<string, mixed>> $records */
    private function validateWarnings(array $records): void
    {
        foreach ($records as $record) {
            $this->requiredString($record, 'code');
            $this->requiredString($record, 'message');

            foreach (['count', 'suppressed_count'] as $key) {
                if (array_key_exists($key, $record) && (! is_int($record[$key]) || $record[$key] < 0)) {
                    throw new TerminalParserException('Parser warning metadata is invalid.');
                }
            }
        }
    }
}
