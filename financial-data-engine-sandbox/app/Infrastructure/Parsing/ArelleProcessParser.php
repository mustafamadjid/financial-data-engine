<?php

namespace App\Infrastructure\Parsing;

use App\Domain\FinancialData\Parsing\Contracts\XbrlParser;
use App\Domain\FinancialData\Parsing\Exceptions\TerminalParserException;
use App\Domain\FinancialData\Parsing\Exceptions\TransientParserException;
use App\Domain\FinancialData\Parsing\ParsedFilingData;
use App\Domain\FinancialData\Parsing\ParserExecutionContext;
use App\Domain\FinancialData\Parsing\ParserOutputValidator;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use JsonException;
use Throwable;

final class ArelleProcessParser implements XbrlParser
{
    public function __construct(private readonly ParserOutputValidator $validator) {}

    public function parse(string $artifactPath, ParserExecutionContext $context): ParsedFilingData
    {
        $command = array_values(array_map('strval', (array) config('financial-pipeline.parser.command', [])));

        if ($command === []) {
            throw new TerminalParserException('Parser command is not configured.');
        }

        $command = array_merge($command, [
            '--input', $artifactPath,
            '--filing-id', $context->filingId,
            '--correlation-id', $context->correlationId,
        ]);

        try {
            $result = Process::path((string) config('financial-pipeline.parser.working_directory'))
                ->timeout((int) config('financial-pipeline.parser.process_timeout', config('financial-pipeline.stages.PARSE.timeout', 900)))
                ->run($command);
        } catch (ProcessTimedOutException $exception) {
            throw new TransientParserException('Parser process timed out.', 0, $exception);
        } catch (Throwable $exception) {
            throw new TransientParserException('Parser process could not be started.', 0, $exception);
        }

        $output = trim($result->output());

        if ($output === '') {
            throw new TransientParserException('Parser process returned no structured output.');
        }

        try {
            $payload = json_decode($output, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TerminalParserException('Parser output was not valid JSON.', 0, $exception);
        }

        if (! is_array($payload)) {
            throw new TerminalParserException('Parser output must be a JSON object.');
        }

        if (! $result->successful()) {
            $errorCode = data_get($payload, 'errors.0.code');

            if (in_array($errorCode, ['INTERNAL_ERROR', 'SERIALIZATION_ERROR'], true)) {
                throw new TransientParserException('Parser process failed due to infrastructure error.');
            }

            throw new TerminalParserException('Parser rejected the source artifact.');
        }

        return $this->validator->validate($payload, $context);
    }
}
