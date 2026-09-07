<?php

namespace App\Services\Pipeline;

use App\Domain\FinancialData\Parsing\ParsedFilingData;
use App\Domain\FinancialData\Parsing\ParserExecutionContext;
use App\Models\Filing;
use App\Models\RawFact;
use App\Models\XbrlContext;
use App\Models\XbrlDimension;
use App\Models\XbrlUnit;
use Illuminate\Support\Facades\DB;

final class ParsedFilingPersistence
{
    public function persist(Filing $filing, ParsedFilingData $data, ParserExecutionContext $context): void
    {
        DB::transaction(function () use ($filing, $data, $context): void {
            $contextIds = [];
            $unitIds = [];

            foreach ($data->contexts as $record) {
                $stored = XbrlContext::query()->firstOrCreate(
                    [
                        'filing_id' => $filing->filing_id,
                        'parser_version' => $context->parserVersion,
                        'parser_config_version' => $context->parserConfigVersion,
                        'source_context_id' => $record['source_context_id'],
                    ],
                    [
                        'context_id' => $this->storedId('ctx', $filing->filing_id, $context, (string) $record['context_id']),
                        'entity_identifier' => $record['entity_identifier'],
                        'scope' => $record['scope'] ?? null,
                        'period_type' => $record['period_type'],
                        'instant_date' => $record['instant_date'] ?? null,
                        'start_date' => $record['start_date'] ?? null,
                        'end_date' => $record['end_date'] ?? null,
                        'context_status' => $record['context_status'],
                    ],
                );
                $contextIds[(string) $record['context_id']] = $stored->context_id;
            }

            foreach ($data->units as $record) {
                $stored = XbrlUnit::query()->firstOrCreate(
                    [
                        'filing_id' => $filing->filing_id,
                        'parser_version' => $context->parserVersion,
                        'parser_config_version' => $context->parserConfigVersion,
                        'source_unit_id' => $record['source_unit_id'],
                    ],
                    [
                        'unit_id' => $this->storedId('unit', $filing->filing_id, $context, (string) $record['unit_id']),
                        'unit_type' => $record['unit_type'],
                        'measure' => $record['measure'] ?? null,
                        'currency' => $record['currency'] ?? null,
                    ],
                );
                $unitIds[(string) $record['unit_id']] = $stored->unit_id;
            }

            foreach ($data->dimensions as $record) {
                XbrlDimension::query()->firstOrCreate(
                    ['dimension_id' => $this->storedId('dim', $filing->filing_id, $context, (string) $record['dimension_id'])],
                    [
                        'context_id' => $contextIds[$record['context_id']],
                        'axis' => $record['axis'],
                        'member' => $record['member'] ?? null,
                        'typed_value' => $record['typed_value'] ?? null,
                        'parser_version' => $context->parserVersion,
                        'parser_config_version' => $context->parserConfigVersion,
                    ],
                );
            }

            foreach ($data->facts as $record) {
                RawFact::query()->firstOrCreate(
                    ['raw_fact_id' => $this->storedId('fact', $filing->filing_id, $context, (string) $record['raw_fact_id'])],
                    [
                        'filing_id' => $filing->filing_id,
                        'source_concept' => $record['source_concept'],
                        'source_namespace' => $record['source_namespace'] ?? null,
                        'raw_value' => $record['raw_value'],
                        'normalized_numeric_value' => $record['normalized_numeric_value'] ?? null,
                        'context_ref' => $contextIds[$record['context_ref']],
                        'unit_ref' => $record['unit_ref'] === null ? null : $unitIds[$record['unit_ref']],
                        'decimals' => $record['decimals'] ?? null,
                        'precision' => $record['precision'] ?? null,
                        'is_nil' => $record['is_nil'],
                        'fact_status' => $record['fact_status'],
                        'parser_version' => $context->parserVersion,
                        'parser_config_version' => $context->parserConfigVersion,
                    ],
                );
            }
        });
    }

    private function storedId(string $prefix, string $filingId, ParserExecutionContext $context, string $sourceId): string
    {
        return $prefix.'_'.substr(hash('sha256', implode('|', [
            $filingId,
            $context->sourceHash,
            $context->parserVersion,
            $context->parserConfigVersion,
            $sourceId,
        ])), 0, 24);
    }
}
