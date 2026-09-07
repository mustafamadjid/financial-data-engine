<?php

use App\Domain\FinancialData\Parsing\Contracts\XbrlParser;
use App\Domain\FinancialData\Parsing\Exceptions\TerminalParserException;
use App\Domain\FinancialData\Parsing\Exceptions\TransientParserException;
use App\Domain\FinancialData\Parsing\ParsedFilingData;
use App\Domain\FinancialData\Parsing\ParserExecutionContext;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\RawFact;
use App\Models\XbrlContext;
use App\Models\XbrlDimension;
use App\Models\XbrlUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('persists validated raw extraction and dispatches normalization after commit', function () {
    Storage::fake('local');
    Queue::fake();
    $filing = makeParseFiling('FIL-PARSE-1');
    bindParseFake(parserData: makeParseData($filing->filing_id, $filing->source_hash));

    runParseJob($filing);

    expect(XbrlContext::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(XbrlUnit::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(XbrlDimension::query()->whereHas('context', fn ($query) => $query->where('filing_id', $filing->filing_id))->count())->toBe(1)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Normalizing->value)
        ->and(PipelineJobRun::query()->where('filing_id', $filing->filing_id)->first()?->status)->toBe('SUCCEEDED');

    Queue::assertPushed(NormalizeFactsJob::class, fn (NormalizeFactsJob $job): bool => $job->filingId === $filing->filing_id);
});

it('does not duplicate an extraction when parse is rerun with the same versions', function () {
    Storage::fake('local');
    Queue::fake();
    $filing = makeParseFiling('FIL-PARSE-2');
    $parserCalls = bindParseFake(parserData: makeParseData($filing->filing_id, $filing->source_hash));

    runParseJob($filing);
    runParseJob($filing->fresh());

    expect(XbrlContext::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and($parserCalls())->toBe(1)
        ->and(Queue::pushed(NormalizeFactsJob::class))->toHaveCount(1);
});

it('preserves the old extraction when parser version changes', function () {
    Storage::fake('local');
    Queue::fake();
    $filing = makeParseFiling('FIL-PARSE-VERSION');
    bindParseFake(parserData: makeParseData($filing->filing_id, $filing->source_hash));
    runParseJob($filing);

    config(['financial-pipeline.parser.version' => '2.0.0']);
    $filing->refresh()->forceFill(['processing_stage' => PipelineStage::Parsing->value])->save();
    bindParseFake(parserData: makeParseData($filing->filing_id, $filing->source_hash, 'AssetsV2', '2.0.0'));
    runParseJob($filing->fresh());

    expect(XbrlContext::query()->where('filing_id', $filing->filing_id)->count())->toBe(2)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(2)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->pluck('parser_version')->unique()->values()->all())->toBe(['1.0.0', '2.0.0']);
});

it('marks deterministic parser output failure and does not dispatch normalization', function () {
    Storage::fake('local');
    Queue::fake();
    $filing = makeParseFiling('FIL-PARSE-INVALID');
    app()->bind(XbrlParser::class, fn () => new class implements XbrlParser
    {
        public function parse(string $artifactPath, ParserExecutionContext $context): ParsedFilingData
        {
            throw new TerminalParserException('Invalid parser contract.');
        }
    });

    runParseJob($filing);

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Failed->value)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(0);
    Queue::assertNotPushed(NormalizeFactsJob::class);
});

it('leaves the stage retryable when parser infrastructure fails', function () {
    Storage::fake('local');
    $filing = makeParseFiling('FIL-PARSE-RETRY');
    app()->bind(XbrlParser::class, fn () => new class implements XbrlParser
    {
        public function parse(string $artifactPath, ParserExecutionContext $context): ParsedFilingData
        {
            throw new TransientParserException('Parser process timed out.');
        }
    });

    expect(fn () => runParseJob($filing))->toThrow(TransientParserException::class)
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Parsing->value)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(0);
});

it('uses parse queue retry, timeout, backoff, and overlap settings', function () {
    $job = new ParseXbrlJob('FIL-PARSE-CONFIG');

    expect($job->queue)->toBe('filing-parse')
        ->and($job->tries)->toBe(2)
        ->and($job->timeout)->toBe(900)
        ->and($job->backoff())->toBe([60])
        ->and($job->middleware())->toHaveCount(1);
});

it('rejects an unsafe filing identifier before queue execution', function () {
    expect(fn () => new ParseXbrlJob('../unsafe-filing'))
        ->toThrow(InvalidArgumentException::class);
});

function makeParseFiling(string $filingId): Filing
{
    $contents = 'parse-artifact-'.$filingId;
    $hash = hash('sha256', $contents);
    $storagePath = 'financial-pipeline/artifacts/ANTM/2026-06-30/1/'.$hash.'/'.$filingId.'.zip';

    Storage::disk('local')->put($storagePath, $contents);

    $filing = Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'ANTM',
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_end' => '2026-06-30',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'storage_path' => $storagePath,
        'source_hash' => $hash,
        'revision_number' => 1,
        'discovered_at' => now(),
        'downloaded_at' => now(),
        'processing_stage' => PipelineStage::Parsing->value,
        'quality_status' => 'PENDING',
    ]);

    FilingArtifact::query()->create([
        'artifact_id' => 'ART-'.$filingId,
        'filing_id' => $filingId,
        'artifact_type' => 'XBRL_INSTANCE',
        'source_hash' => $hash,
        'storage_path' => $storagePath,
        'original_filename' => $filingId.'.zip',
        'content_type' => 'application/zip',
        'size_bytes' => strlen($contents),
        'downloaded_at' => now(),
    ]);

    PipelineRun::query()->create([
        'filing_id' => $filingId,
        'trigger' => 'DISCOVERY',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);

    return $filing;
}

function bindParseFake(ParsedFilingData $parserData): Closure
{
    $calls = (object) ['value' => 0];
    app()->bind(XbrlParser::class, function () use ($calls, $parserData): XbrlParser {
        return new class($calls, $parserData) implements XbrlParser
        {
            public function __construct(private readonly object $calls, private readonly ParsedFilingData $parserData) {}

            public function parse(string $artifactPath, ParserExecutionContext $context): ParsedFilingData
            {
                $this->calls->value++;

                return $this->parserData;
            }
        };
    });

    return fn (): int => $calls->value;
}

function makeParseData(string $filingId, string $sourceHash, string $concept = 'Assets', string $parserVersion = '1.0.0'): ParsedFilingData
{
    return new ParsedFilingData(
        filingId: $filingId,
        sourceHash: $sourceHash,
        parserVersion: $parserVersion,
        parserConfigVersion: '1.0.0',
        runtime: ['worker_version' => $parserVersion, 'arelle_version' => '2.44.4'],
        contexts: [[
            'context_id' => 'ctx-source-1', 'filing_id' => $filingId, 'source_context_id' => 'ctx-source-1',
            'entity_identifier' => 'ANTM', 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT',
            'instant_date' => '2026-06-30', 'start_date' => null, 'end_date' => null, 'context_status' => 'RESOLVED',
        ]],
        units: [[
            'unit_id' => 'unit-source-1', 'filing_id' => $filingId, 'source_unit_id' => 'IDR',
            'unit_type' => 'CURRENCY', 'measure' => 'iso4217:IDR', 'currency' => 'IDR',
        ]],
        dimensions: [[
            'dimension_id' => 'dim-source-1', 'context_id' => 'ctx-source-1', 'axis' => 'SegmentAxis',
            'member' => 'SegmentMember', 'typed_value' => null,
        ]],
        facts: [[
            'raw_fact_id' => 'fact-source-1', 'filing_id' => $filingId, 'source_concept' => $concept,
            'source_namespace' => 'idx', 'raw_value' => '100', 'normalized_numeric_value' => '100',
            'context_ref' => 'ctx-source-1', 'unit_ref' => 'unit-source-1', 'decimals' => '0', 'precision' => null,
            'is_nil' => false, 'fact_status' => 'EXTRACTED',
        ]],
    );
}

function runParseJob(Filing $filing): void
{
    app()->call([new ParseXbrlJob($filing->filing_id), 'handle']);
}
