<?php

namespace App\Jobs\Pipeline;

use App\Application\Pipeline\PipelineOrchestrator;
use App\Domain\FinancialData\Parsing\Contracts\XbrlParser;
use App\Domain\FinancialData\Parsing\Exceptions\TerminalParserException;
use App\Domain\FinancialData\Parsing\ParsedFilingData;
use App\Domain\FinancialData\Parsing\ParserExecutionContext;
use App\Domain\FinancialData\Pipeline\PipelineIdempotencyKey;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Infrastructure\Storage\FilingArtifactStorage;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use App\Models\AuditLog;
use App\Models\Filing;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Services\Pipeline\JobExecutionRecorder;
use App\Services\Pipeline\ParsedFilingPersistence;
use App\Services\Pipeline\PipelineExecutionLogger;
use App\Services\Pipeline\PipelineFailureHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ParseXbrlJob extends PipelineJob
{
    public int $tries;

    public int $timeout;

    public function __construct(string $filingId)
    {
        $filingId = trim($filingId);

        if ($filingId === '' || strlen($filingId) > 128 || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $filingId) !== 1) {
            throw new InvalidArgumentException('A valid filing identifier is required.');
        }

        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.PARSE.queue', 'filing-parse'));
        $this->tries = (int) config('financial-pipeline.stages.PARSE.tries', 2);
        $this->timeout = (int) config('financial-pipeline.stages.PARSE.timeout', 900);
    }

    public function backoff(): array
    {
        return array_values((array) config('financial-pipeline.stages.PARSE.backoff', [60]));
    }

    public function middleware(): array
    {
        return [new PipelineOverlapMiddleware($this->filingId, PipelineStage::Parsing, $this->timeout)];
    }

    public function handle(
        XbrlParser $parser,
        FilingArtifactStorage $artifactStorage,
        ParsedFilingPersistence $persistence,
        JobExecutionRecorder $executionRecorder,
        PipelineExecutionLogger $pipelineLogger,
        PipelineOrchestrator $orchestrator,
    ): void {
        $filing = Filing::query()->find($this->filingId);

        if ($filing === null) {
            throw new RuntimeException('Filing was not found.');
        }

        $parserVersion = (string) config('financial-pipeline.parser.version', '1.0.0');
        $parserConfigVersion = (string) config('financial-pipeline.parser.config_version', '1.0.0');
        $idempotencyKey = PipelineIdempotencyKey::parse(
            $filing->filing_id,
            (string) $filing->source_hash,
            $parserVersion,
            $parserConfigVersion,
        )->value();
        $successfulRun = PipelineJobRun::query()
            ->where('filing_id', $filing->filing_id)
            ->where('stage', PipelineStage::Parsing->value)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'SUCCEEDED')
            ->exists();

        if (PipelineStage::tryFrom((string) $filing->processing_stage) !== PipelineStage::Parsing) {
            if ($successfulRun) {
                return;
            }

            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Parsing,
                new TerminalParserException('Filing is not ready for parsing.'),
            );

            return;
        }

        $artifact = $artifactStorage->validArtifactForFiling($filing);

        if ($artifact === null) {
            $orchestrator->markFailed(
                $filing->filing_id,
                PipelineStage::Parsing,
                new TerminalParserException('A valid immutable filing artifact was not found.'),
            );

            return;
        }

        $pipelineRun = PipelineRun::query()->where('filing_id', $filing->filing_id)->latest('id')->first();

        if ($pipelineRun === null) {
            $pipelineRun = PipelineRun::query()->create([
                'filing_id' => $filing->filing_id,
                'trigger' => 'PARSE',
                'status' => 'RUNNING',
                'correlation_id' => (string) Str::uuid(),
                'started_at' => now(),
            ]);
        }

        $jobRun = $executionRecorder->queued(
            filingId: $filing->filing_id,
            stage: PipelineStage::Parsing->value,
            jobClass: self::class,
            queueName: (string) $this->queue,
            idempotencyKey: $idempotencyKey,
            pipelineRunId: $pipelineRun->id,
            correlationId: $pipelineRun->correlation_id,
        );
        $executionRecorder->running($jobRun);
        $this->logExecutionContext($pipelineLogger, $jobRun, $pipelineRun, PipelineStage::Parsing, [
            'parser_version' => $parserVersion,
            'parser_config_version' => $parserConfigVersion,
            'contract_version' => (string) config('financial-pipeline.contract_version', '1.0.0'),
        ]);
        $context = new ParserExecutionContext(
            filingId: $filing->filing_id,
            sourceHash: (string) $filing->source_hash,
            parserVersion: $parserVersion,
            parserConfigVersion: $parserConfigVersion,
            correlationId: (string) ($jobRun->correlation_id ?? $pipelineRun->correlation_id),
            contractVersion: (string) config('financial-pipeline.contract_version', '1.0.0'),
        );

        try {
            $parsed = $parser->parse($artifactStorage->absolutePath($artifact), $context);

            $this->assertParsedDataMatchesContext($parsed, $context);
            $persistence->persist($filing, $parsed, $context);

            DB::transaction(function () use ($filing, $parsed, $context, $executionRecorder, $jobRun, $orchestrator): void {
                $current = Filing::query()->lockForUpdate()->findOrFail($filing->filing_id);
                $current->forceFill(['processing_stage' => PipelineStage::Parsed->value])->save();
                $executionRecorder->succeeded($jobRun);

                AuditLog::create([
                    'actor_id' => 'system',
                    'action' => 'filing.parsed',
                    'entity_type' => Filing::class,
                    'entity_id' => $current->filing_id,
                    'new_value' => [
                        'source_hash' => $context->sourceHash,
                        'parser_version' => $context->parserVersion,
                        'parser_config_version' => $context->parserConfigVersion,
                        'contexts' => count($parsed->contexts),
                        'units' => count($parsed->units),
                        'dimensions' => count($parsed->dimensions),
                        'facts' => count($parsed->facts),
                    ],
                    'rationale' => 'Raw XBRL extraction validated and persisted.',
                    'filing_id' => $current->filing_id,
                    'correlation_id' => $jobRun->correlation_id,
                ]);

                $orchestrator->dispatchNext($current->filing_id, PipelineStage::Parsed);
            });
        } catch (TerminalParserException $exception) {
            $orchestrator->markFailed($filing->filing_id, PipelineStage::Parsing, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(PipelineFailureHandler::class)->handle($this->filingId, PipelineStage::Parsing, $exception);
        } catch (Throwable) {
            // Do not mask the queue worker's original parser failure.
        }
    }

    private function assertParsedDataMatchesContext(ParsedFilingData $parsed, ParserExecutionContext $context): void
    {
        if ($parsed->filingId !== $context->filingId || ! hash_equals($parsed->sourceHash, $context->sourceHash) || $parsed->parserVersion !== $context->parserVersion || $parsed->parserConfigVersion !== $context->parserConfigVersion) {
            throw new TerminalParserException('Parsed filing data does not match the execution context.');
        }
    }
}
