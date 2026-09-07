<?php

use App\Domain\FinancialData\Parsing\Exceptions\TerminalParserException;
use App\Domain\FinancialData\Parsing\Exceptions\TransientParserException;
use App\Domain\FinancialData\Parsing\ParserExecutionContext;
use App\Domain\FinancialData\Parsing\ParserOutputValidator;
use App\Infrastructure\Parsing\ArelleProcessParser;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

it('runs the configured worker and returns a validated parser result', function () {
    Process::fake(fn () => Process::result(json_encode(parseBridgePayload(), JSON_THROW_ON_ERROR), 'worker log', 0));

    $result = app(ArelleProcessParser::class)->parse(
        'financial-pipeline/artifacts/ANTM/2026-06-30/1/hash/file.zip',
        parseBridgeContext(),
    );

    expect($result->filingId)->toBe('FIL-PARSER-1')
        ->and($result->facts)->toHaveCount(1);

    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('--input', $process->command, true)
        && in_array('--filing-id', $process->command, true)
        && in_array('--correlation-id', $process->command, true));
});

it('rejects malformed JSON from the worker as terminal parser failure', function () {
    Process::fake(fn () => Process::result('{malformed', '', 0));

    expect(fn () => app(ArelleProcessParser::class)->parse('artifact.zip', parseBridgeContext()))
        ->toThrow(TerminalParserException::class);
});

it('rejects a parser contract identity mismatch before persistence', function () {
    $payload = parseBridgePayload();
    $payload['filing_id'] = 'OTHER-FILING';
    Process::fake(fn () => Process::result(json_encode($payload, JSON_THROW_ON_ERROR), '', 0));

    expect(fn () => app(ArelleProcessParser::class)->parse('artifact.zip', parseBridgeContext()))
        ->toThrow(TerminalParserException::class);
});

it('classifies a crashed worker without structured output as retryable', function () {
    Process::fake(fn () => Process::result('', 'segmentation fault', 139));

    expect(fn () => app(ArelleProcessParser::class)->parse('artifact.zip', parseBridgeContext()))
        ->toThrow(TransientParserException::class);
});

it('rejects an invalid contract version', function () {
    $payload = parseBridgePayload();
    $payload['parser_contract_version'] = '9.0.0';
    Process::fake(fn () => Process::result(json_encode($payload, JSON_THROW_ON_ERROR), '', 0));

    expect(fn () => app(ParserOutputValidator::class)->validate($payload, parseBridgeContext()))
        ->toThrow(TerminalParserException::class);
});

function parseBridgeContext(): ParserExecutionContext
{
    return new ParserExecutionContext(
        filingId: 'FIL-PARSER-1',
        sourceHash: hash('sha256', 'parser-artifact'),
        parserVersion: '1.0.0',
        parserConfigVersion: '1.0.0',
        correlationId: 'corr-parser-1',
        contractVersion: '1.0.0',
    );
}

/** @return array<string, mixed> */
function parseBridgePayload(): array
{
    $hash = hash('sha256', 'parser-artifact');

    return [
        'parser_contract' => 'xbrl_parser_result',
        'parser_contract_version' => '1.0.0',
        'status' => 'SUCCESS',
        'filing_id' => 'FIL-PARSER-1',
        'source' => ['path' => 'artifact.zip', 'sha256' => $hash],
        'runtime' => ['worker_version' => '1.0.0', 'arelle_version' => '2.44.4', 'python_version' => '3.13.0'],
        'counts' => ['contexts' => 1, 'units' => 1, 'dimensions' => 1, 'facts' => 1],
        'contexts' => [[
            'context_id' => 'ctx-source-1', 'filing_id' => 'FIL-PARSER-1', 'source_context_id' => 'ctx-source-1',
            'entity_identifier' => 'ANTM', 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT',
            'instant_date' => '2026-06-30', 'start_date' => null, 'end_date' => null, 'context_status' => 'RESOLVED',
        ]],
        'units' => [[
            'unit_id' => 'unit-source-1', 'filing_id' => 'FIL-PARSER-1', 'source_unit_id' => 'IDR',
            'unit_type' => 'CURRENCY', 'measure' => 'iso4217:IDR', 'currency' => 'IDR',
        ]],
        'dimensions' => [[
            'dimension_id' => 'dim-source-1', 'context_id' => 'ctx-source-1', 'axis' => 'SegmentAxis',
            'member' => 'SegmentMember', 'typed_value' => null,
        ]],
        'facts' => [[
            'raw_fact_id' => 'fact-source-1', 'filing_id' => 'FIL-PARSER-1', 'source_concept' => 'Assets',
            'source_namespace' => 'idx', 'raw_value' => '100', 'normalized_numeric_value' => '100',
            'context_ref' => 'ctx-source-1', 'unit_ref' => 'unit-source-1', 'decimals' => '0', 'precision' => null,
            'is_nil' => false, 'fact_status' => 'EXTRACTED',
        ]],
        'warnings' => [],
        'errors' => [],
    ];
}
