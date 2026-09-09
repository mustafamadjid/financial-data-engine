<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineJobRun;
use App\Models\PipelineRun;
use App\Models\PublishedSnapshot;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\XbrlContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('publishes a verified filing once and reuses the same identity on rerun', function () {
    Queue::fake();
    $filing = makePublishFiling('FIL-PUBLISH-1', 'VERIFIED');

    app()->call([new PublishFilingJob($filing->filing_id), 'handle']);
    $snapshotBefore = PublishedSnapshot::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray();
    $auditBefore = AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.published')->firstOrFail()->toArray();
    app()->call([new PublishFilingJob($filing->filing_id), 'handle']);

    expect(PublishedSnapshot::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(PublishedSnapshot::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray())->toBe($snapshotBefore)
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Published->value)
        ->and(PipelineJobRun::query()->where('filing_id', $filing->filing_id)->where('stage', 'PUBLISHING')->count())->toBe(1)
        ->and(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.published')->count())->toBe(1)
        ->and(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.published')->firstOrFail()->toArray())->toBe($auditBefore)
        ->and($auditBefore['entity_id'])->toBe($snapshotBefore['snapshot_id'])
        ->and((new PublishFilingJob($filing->filing_id))->connection)->toBe('redis-publish')
        ->and((new PublishFilingJob($filing->filing_id))->queue)->toBe('publish');
});

it('blocks failed and review-required filings from publish', function (string $qualityStatus) {
    Queue::fake();
    $filing = makePublishFiling('FIL-PUBLISH-'.str_replace('_', '-', $qualityStatus), $qualityStatus);

    app()->call([new PublishFilingJob($filing->filing_id), 'handle']);

    expect(PublishedSnapshot::query()->where('filing_id', $filing->filing_id)->exists())->toBeFalse()
        ->and($filing->fresh()->processing_stage)->not->toBe(PipelineStage::Published->value)
        ->and(Queue::pushed(PublishFilingJob::class))->toHaveCount(0);
})->with(['FAILED', 'REVIEW_REQUIRED']);

it('uses the configured publish retry policy and overlap protection', function () {
    $job = new PublishFilingJob('FIL-PUBLISH-CONFIG');

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->backoff())->toBe([30, 120])
        ->and($job->middleware())->toHaveCount(1);
});

it('adds structured execution context to publish logs', function () {
    Log::spy();
    $filing = makePublishFiling('FIL-PUBLISH-LOG', 'VERIFIED');

    app()->call([new PublishFilingJob($filing->filing_id), 'handle']);

    Log::shouldHaveReceived('withContext')
        ->once()
        ->withArgs(fn (array $context): bool => $context['filing_id'] === $filing->filing_id
            && $context['stage'] === PipelineStage::Publishing->value
            && $context['job'] === PublishFilingJob::class
            && $context['queue'] === 'filing-publish');
});

function makePublishFiling(string $filingId, string $qualityStatus): Filing
{
    $filing = Filing::query()->create([
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
        'processing_stage' => PipelineStage::Publishing->value,
        'quality_status' => $qualityStatus,
    ]);
    CanonicalConcept::query()->create([
        'code' => 'total_assets_'.$filingId,
        'name' => 'Total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-'.$filingId,
        'source_concept' => 'Assets',
        'canonical_concept' => 'total_assets_'.$filingId,
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-'.$filingId,
        'filing_id' => $filingId,
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
        'raw_fact_id' => 'RAW-'.$filingId,
        'filing_id' => $filingId,
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
    $normalized = NormalizedFact::query()->create([
        'normalized_fact_id' => 'NF-'.$filingId,
        'filing_id' => $filingId,
        'raw_fact_id' => $raw->raw_fact_id,
        'issuer_code' => 'TEST',
        'period' => 'FY',
        'period_end' => '2025-12-31',
        'canonical_concept' => 'total_assets_'.$filingId,
        'value' => '100',
        'currency' => 'IDR',
        'scope' => 'CONSOLIDATED',
        'data_type' => 'REPORTED',
        'source_concept' => 'Assets',
        'mapping_rule_id' => 'MAP-'.$filingId,
        'mapping_rule_version' => 1,
        'normalization_version' => '1.0.0@mapping-1',
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'VERIFIED',
    ]);
    ValidationResult::query()->create([
        'validation_result_id' => 'VR-'.$filingId,
        'filing_id' => $filingId,
        'normalized_dataset_version' => 'dataset-'.$filingId,
        'validation_rule_set_version' => 'rules-1',
        'normalized_fact_ids' => [$normalized->normalized_fact_id],
        'rule_code' => 'ASSET-001',
        'rule_version' => 1,
        'result' => 'PASS',
        'severity' => 'INFO',
        'message' => 'Passed.',
        'checked_at' => now(),
    ]);
    PipelineRun::query()->create([
        'filing_id' => $filingId,
        'trigger' => 'VALIDATE',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);

    return $filing;
}
