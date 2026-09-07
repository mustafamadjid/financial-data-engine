<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\RawFact;
use App\Models\XbrlContext;
use App\Models\XbrlUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    config(['financial-pipeline.normalization.mapping_version' => null]);
});

it('persists normalized facts with lineage and dispatches validation after commit', function () {
    Queue::fake();
    $filing = makeNormalizeFiling('FIL-NORMALIZE-1');
    makeNormalizeRawFact($filing, 'Assets', 'CONSOLIDATED', 'INSTANT', '100');
    makeNormalizeMapping('MAP-NORMALIZE-1', 'Assets', 1);

    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);

    $normalized = NormalizedFact::query()->where('filing_id', $filing->filing_id)->first();

    expect($normalized)->not->toBeNull()
        ->and($normalized->raw_fact_id)->toBe('RAW-1')
        ->and($normalized->mapping_rule_id)->toBe('MAP-NORMALIZE-1')
        ->and($normalized->canonical_concept)->toBe('total_assets')
        ->and($normalized->value)->toBe('100.000000000000000000')
        ->and($normalized->normalization_status)->toBe('NORMALIZED')
        ->and($normalized->validation_status)->toBe('PENDING')
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Validating->value)
        ->and(PipelineJobRun::query()->where('filing_id', $filing->filing_id)->where('stage', 'NORMALIZING')->first()?->status)->toBe('SUCCEEDED');

    Queue::assertPushed(ValidateFilingJob::class, fn (ValidateFilingJob $job): bool => $job->filingId === $filing->filing_id);
});

it('is idempotent for the same mapping version and preserves raw facts', function () {
    Queue::fake();
    $filing = makeNormalizeFiling('FIL-NORMALIZE-2');
    makeNormalizeRawFact($filing, 'Assets', 'CONSOLIDATED', 'INSTANT', '100');
    makeNormalizeMapping('MAP-NORMALIZE-2', 'Assets', 1);

    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);
    $rawBefore = RawFact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray();
    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);

    expect(NormalizedFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray())->toBe($rawBefore)
        ->and(Queue::pushed(ValidateFilingJob::class))->toHaveCount(1);
});

it('creates a new normalized history when the mapping version changes', function () {
    Queue::fake();
    $filing = makeNormalizeFiling('FIL-NORMALIZE-VERSION');
    makeNormalizeRawFact($filing, 'Assets', 'CONSOLIDATED', 'INSTANT', '100');
    makeNormalizeMapping('MAP-NORMALIZE-V1', 'Assets', 1, 'total_assets');

    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);

    makeNormalizeMapping('MAP-NORMALIZE-V2', 'Assets', 2, 'total_assets_v2');
    config(['financial-pipeline.normalization.mapping_version' => 2]);
    $filing->refresh()->forceFill(['processing_stage' => PipelineStage::Normalizing->value])->save();
    PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'NORMALIZE',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);

    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);

    expect(NormalizedFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(2)
        ->and(NormalizedFact::query()->where('filing_id', $filing->filing_id)->pluck('mapping_rule_id')->sort()->values()->all())->toBe(['MAP-NORMALIZE-V1', 'MAP-NORMALIZE-V2'])
        ->and(RawFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(1);
});

it('records unmapped facts explicitly and keeps them out of canonical output', function () {
    Queue::fake();
    $filing = makeNormalizeFiling('FIL-NORMALIZE-UNMAPPED');
    makeNormalizeRawFact($filing, 'UnknownConcept', 'CONSOLIDATED', 'INSTANT', '100');
    makeNormalizeMapping('MAP-NORMALIZE-OTHER', 'Assets', 1);

    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);

    expect(NormalizedFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(0)
        ->and(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'normalization.review_required')->exists())->toBeTrue()
        ->and($filing->fresh()->quality_status)->toBe('REVIEW_REQUIRED')
        ->and(Queue::pushed(ValidateFilingJob::class))->toHaveCount(1);
});

it('uses the normalization queue policy', function () {
    $job = new NormalizeFactsJob('FIL-NORMALIZE-CONFIG');

    expect($job->queue)->toBe('filing-normalize')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300)
        ->and($job->backoff())->toBe([30, 120])
        ->and($job->middleware())->toHaveCount(1);
});

function makeNormalizeFiling(string $filingId): Filing
{
    return Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'ANTM',
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2026,
        'fiscal_period' => 'Q2',
        'period_end' => '2026-06-30',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', $filingId),
        'revision_number' => 1,
        'processing_stage' => PipelineStage::Normalizing->value,
        'quality_status' => 'PENDING',
    ]);
}

function makeNormalizeRawFact(Filing $filing, string $sourceConcept, string $scope, string $periodType, string $value): RawFact
{
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-NORMALIZE-'.substr($filing->filing_id, -1),
        'filing_id' => $filing->filing_id,
        'source_context_id' => 'source-context-'.$filing->filing_id,
        'entity_identifier' => $filing->issuer_code,
        'scope' => $scope,
        'period_type' => $periodType,
        'instant_date' => $periodType === 'INSTANT' ? '2026-06-30' : null,
        'start_date' => $periodType === 'DURATION' ? '2026-01-01' : null,
        'end_date' => $periodType === 'DURATION' ? '2026-06-30' : null,
        'context_status' => 'RESOLVED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $unit = XbrlUnit::query()->create([
        'unit_id' => 'UNIT-NORMALIZE-'.substr($filing->filing_id, -1),
        'filing_id' => $filing->filing_id,
        'source_unit_id' => 'IDR',
        'unit_type' => 'CURRENCY',
        'measure' => 'iso4217:IDR',
        'currency' => 'IDR',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);

    return RawFact::query()->create([
        'raw_fact_id' => 'RAW-'.substr($filing->filing_id, -1),
        'filing_id' => $filing->filing_id,
        'source_concept' => $sourceConcept,
        'source_namespace' => 'idx',
        'raw_value' => $value,
        'normalized_numeric_value' => $value,
        'context_ref' => $context->context_id,
        'unit_ref' => $unit->unit_id,
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
}

function makeNormalizeMapping(string $mappingId, string $sourceConcept, int $version, string $canonicalCode = 'total_assets'): ConceptMapping
{
    CanonicalConcept::query()->firstOrCreate(['code' => $canonicalCode], [
        'name' => $canonicalCode,
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);

    return ConceptMapping::query()->create([
        'mapping_rule_id' => $mappingId,
        'source_concept' => $sourceConcept,
        'canonical_concept' => $canonicalCode,
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => $version,
    ]);
}
