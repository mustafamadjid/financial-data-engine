<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\ReprocessStage;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\FilingArtifact;
use App\Models\NormalizedFact;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\XbrlContext;
use App\Services\Pipeline\ReprocessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('starts normalize reprocess with a new run and preserves raw facts', function () {
    Queue::fake();
    $filing = makeReprocessFiling('FIL-REPROCESS-1');
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-REPROCESS-1',
        'filing_id' => $filing->filing_id,
        'source_context_id' => 'c1',
        'entity_identifier' => 'TEST',
        'scope' => 'CONSOLIDATED',
        'period_type' => 'INSTANT',
        'instant_date' => '2025-12-31',
        'context_status' => 'RESOLVED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $raw = RawFact::query()->create([
        'raw_fact_id' => 'RAW-REPROCESS-1',
        'filing_id' => $filing->filing_id,
        'source_concept' => 'Assets',
        'raw_value' => '100',
        'normalized_numeric_value' => '100',
        'context_ref' => $context->context_id,
        'unit_ref' => null,
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $rawBefore = $raw->fresh()->getRawOriginal();

    $run = app(ReprocessService::class)->start(
        $filing->filing_id,
        ReprocessStage::Normalize,
        'Mapping rule version changed.',
        'operator-1',
    );

    expect($run->started_from_stage)->toBe(ReprocessStage::Normalize->value)
        ->and($run->initiated_by)->toBe('operator-1')
        ->and($run->reason)->toBe('Mapping rule version changed.')
        ->and($run->dependency_versions)->toBeArray()
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Normalizing->value)
        ->and(RawFact::query()->findOrFail($raw->raw_fact_id)->getRawOriginal())->toBe($rawBefore)
        ->and(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.reprocess_requested')->exists())->toBeTrue();

    Queue::assertPushed(NormalizeFactsJob::class, fn (NormalizeFactsJob $job): bool => $job->filingId === $filing->filing_id);
});

it('rejects normalize reprocess without raw facts', function () {
    $filing = makeReprocessFiling('FIL-REPROCESS-MISSING');

    expect(fn () => app(ReprocessService::class)->start(
        $filing->filing_id,
        ReprocessStage::Normalize,
        'Retry after mapping review.',
        'operator-1',
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects an overlapping reprocess for the same filing and stage', function () {
    Queue::fake();
    $filing = makeReprocessFiling('FIL-REPROCESS-OVERLAP');
    seedReprocessRaw($filing);
    $service = app(ReprocessService::class);

    $service->start($filing->filing_id, ReprocessStage::Normalize, 'First run.', 'operator-1');

    expect(fn () => $service->start($filing->filing_id, ReprocessStage::Normalize, 'Second run.', 'operator-2'))
        ->toThrow(InvalidArgumentException::class, 'already active');
});

it('dispatches the correct first job for every supported reprocess stage', function (string $stageValue, string $jobClass) {
    Queue::fake();
    Storage::fake('local');
    $filing = makeReprocessFiling('FIL-REPROCESS-'.str_replace('_', '-', strtolower($stageValue)));
    $stage = ReprocessStage::from($stageValue);

    match ($stage) {
        ReprocessStage::Download => null,
        ReprocessStage::Parse => seedReprocessArtifact($filing),
        ReprocessStage::Normalize => seedReprocessRaw($filing),
        ReprocessStage::Validate => seedReprocessNormalized($filing),
        ReprocessStage::Publish => seedReprocessValidation($filing),
    };

    $run = app(ReprocessService::class)->start($filing->filing_id, $stage, 'Operational reprocess test.', 'operator-1');

    expect($run->started_from_stage)->toBe($stageValue)
        ->and($filing->fresh()->quality_status)->toBe($stage === ReprocessStage::Publish ? 'VERIFIED' : 'PENDING');
    Queue::assertPushed($jobClass, fn (object $job): bool => $job->filingId === $filing->filing_id);
})->with([
    ['DOWNLOAD', DownloadFilingJob::class],
    ['PARSE', ParseXbrlJob::class],
    ['NORMALIZE', NormalizeFactsJob::class],
    ['VALIDATE', ValidateFilingJob::class],
    ['PUBLISH', PublishFilingJob::class],
]);

function makeReprocessFiling(string $filingId): Filing
{
    return Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_end' => '2025-12-31',
        'source_url' => "https://example.test/{$filingId}.xbrl",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => hash('sha256', $filingId),
        'revision_number' => 1,
        'processing_stage' => PipelineStage::Published->value,
        'quality_status' => 'VERIFIED',
    ]);
}

function seedReprocessArtifact(Filing $filing): void
{
    $contents = 'immutable-artifact';
    $hash = hash('sha256', $contents);
    $path = 'financial-pipeline/artifacts/TEST/2025-12-31/1/'.$hash.'/'.$filing->filing_id.'.xbrl';
    Storage::disk('local')->put($path, $contents);
    FilingArtifact::query()->create([
        'artifact_id' => 'ART-'.$filing->filing_id,
        'filing_id' => $filing->filing_id,
        'artifact_type' => 'XBRL_INSTANCE',
        'source_hash' => $hash,
        'storage_path' => $path,
        'original_filename' => $filing->filing_id.'.xbrl',
        'content_type' => 'application/xbrl+xml',
        'size_bytes' => strlen($contents),
        'downloaded_at' => now(),
    ]);
    $filing->forceFill(['storage_path' => $path, 'source_hash' => $hash])->save();
}

function seedReprocessRaw(Filing $filing): RawFact
{
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-'.$filing->filing_id,
        'filing_id' => $filing->filing_id,
        'source_context_id' => 'c1',
        'entity_identifier' => 'TEST',
        'scope' => 'CONSOLIDATED',
        'period_type' => 'INSTANT',
        'instant_date' => '2025-12-31',
        'context_status' => 'RESOLVED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);

    return RawFact::query()->create([
        'raw_fact_id' => 'RAW-'.$filing->filing_id,
        'filing_id' => $filing->filing_id,
        'source_concept' => 'Assets',
        'raw_value' => '100',
        'normalized_numeric_value' => '100',
        'context_ref' => $context->context_id,
        'unit_ref' => null,
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
}

function seedReprocessNormalized(Filing $filing): NormalizedFact
{
    $raw = seedReprocessRaw($filing);
    $canonicalCode = 'total_assets_'.$filing->filing_id;
    $mappingId = 'MAP-'.$filing->filing_id;
    CanonicalConcept::query()->create([
        'code' => $canonicalCode,
        'name' => 'Total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => $mappingId,
        'source_concept' => 'Assets',
        'canonical_concept' => $canonicalCode,
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);

    return NormalizedFact::query()->create([
        'normalized_fact_id' => 'NF-'.$filing->filing_id,
        'filing_id' => $filing->filing_id,
        'raw_fact_id' => $raw->raw_fact_id,
        'issuer_code' => 'TEST',
        'period' => 'FY',
        'period_end' => '2025-12-31',
        'canonical_concept' => $canonicalCode,
        'value' => '100',
        'currency' => 'IDR',
        'scope' => 'CONSOLIDATED',
        'data_type' => 'REPORTED',
        'source_concept' => 'Assets',
        'mapping_rule_id' => $mappingId,
        'mapping_rule_version' => 1,
        'normalization_version' => '1.0.0@mapping-1',
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'VERIFIED',
    ]);
}

function seedReprocessValidation(Filing $filing): void
{
    $normalized = seedReprocessNormalized($filing);
    ValidationResult::query()->create([
        'validation_result_id' => 'VR-'.$filing->filing_id,
        'filing_id' => $filing->filing_id,
        'normalized_dataset_version' => 'dataset-'.$filing->filing_id,
        'validation_rule_set_version' => 'rules-1',
        'normalized_fact_ids' => [$normalized->normalized_fact_id],
        'rule_code' => 'ASSET-001',
        'rule_version' => 1,
        'result' => 'PASS',
        'severity' => 'INFO',
        'message' => 'Passed.',
        'checked_at' => now(),
    ]);
}
