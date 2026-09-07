<?php

use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\ReprocessStage;
use App\Domain\FinancialData\Validation\Contracts\ValidationRule;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Domain\FinancialData\Validation\RuleResult;
use App\Domain\FinancialData\Validation\ValidationRuleRegistry;
use App\Jobs\Pipeline\DiscoverFilingsJob;
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
use App\Models\PipelineRun;
use App\Models\PublishedSnapshot;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\ValidationRule as ValidationRuleModel;
use App\Models\XbrlContext;
use App\Services\Pipeline\ReprocessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('runs the approved sample end to end and keeps same-version reruns idempotent', function () {
    Queue::fake();
    Storage::fake('local');
    $filingId = 'FIL-E2E-APPROVED';
    $source = 'approved-sample-source';
    configureApprovedDiscovery($filingId);
    Http::fake(['https://example.test/*' => Http::response($source, 200, ['Content-Type' => 'application/zip'])]);
    Process::fake(fn () => Process::result(json_encode(approvedParserPayload($filingId, hash('sha256', $source)), JSON_THROW_ON_ERROR), '', 0));
    createApprovedMappingAndRule();

    runApprovedPipeline($filingId);

    $counts = approvedPipelineCounts($filingId);
    expect(Filing::query()->findOrFail($filingId)->processing_stage)->toBe(PipelineStage::Published->value)
        ->and(Filing::query()->findOrFail($filingId)->quality_status)->toBe('VERIFIED')
        ->and(PublishedSnapshot::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(AuditLog::query()->where('filing_id', $filingId)->where('action', 'filing.published')->exists())->toBeTrue();

    runApprovedPipeline($filingId);

    expect(approvedPipelineCounts($filingId))->toBe($counts)
        ->and(FilingArtifact::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(RawFact::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(NormalizedFact::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(ValidationResult::query()->where('filing_id', $filingId)->count())->toBe(1);
});

it('blocks review-required and failed validation outcomes from publishing', function (string $result, string $severity, string $qualityStatus) {
    Queue::fake();
    $filingId = 'FIL-E2E-'.str_replace('_', '-', strtolower($qualityStatus));
    $filing = seedApprovedNormalizedFiling($filingId);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'E2E-RULE' => approvedRule('E2E-RULE', $result, $severity),
    ]));
    ValidationRuleModel::query()->create([
        'rule_code' => 'E2E-RULE',
        'description' => 'E2E quality outcome.',
        'severity' => $severity,
        'inputs' => ['canonical_concept' => 'total_assets'],
        'enabled' => true,
        'rule_version' => 1,
    ]);
    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);
    expect($filing->fresh()->quality_status)->toBe($qualityStatus)
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Validated->value);

    if ($qualityStatus === 'REVIEW_REQUIRED') {
        expect(AuditLog::query()->where('filing_id', $filing->filing_id)->where('action', 'filing.review_required')->exists())->toBeTrue();
    }

    app()->call([new PublishFilingJob($filing->filing_id), 'handle']);
    expect(PublishedSnapshot::query()->where('filing_id', $filing->filing_id)->exists())->toBeFalse();
})->with([
    ['REVIEW_REQUIRED', 'WARN', 'REVIEW_REQUIRED'],
    ['FAIL', 'ERROR', 'FAILED'],
]);

it('reprocesses mapping and validation versions without changing upstream history', function () {
    Queue::fake();
    $filing = seedApprovedNormalizedFiling('FIL-E2E-VERSIONED');
    $rawBefore = RawFact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->getRawOriginal();
    $normalizedBefore = NormalizedFact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->getRawOriginal();
    ValidationRuleModel::query()->create([
        'rule_code' => 'E2E-RULE',
        'description' => 'E2E versioned rule.',
        'severity' => 'INFO',
        'inputs' => ['canonical_concept' => 'total_assets'],
        'enabled' => true,
        'rule_version' => 1,
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-E2E-V2-'.$filing->filing_id,
        'source_concept' => 'Assets',
        'canonical_concept' => 'total_assets',
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 2,
    ]);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'E2E-RULE' => approvedRule('E2E-RULE', 'PASS', 'INFO'),
    ]));

    $reprocess = app(ReprocessService::class);
    config(['financial-pipeline.normalization.mapping_version' => '2']);
    $reprocess->start($filing->filing_id, ReprocessStage::Normalize, 'Mapping changed.', 'operator-1');
    expect(Queue::pushed(NormalizeFactsJob::class))->toHaveCount(1);
    $filing->refresh()->forceFill(['processing_stage' => PipelineStage::Normalizing->value])->save();
    app()->call([new NormalizeFactsJob($filing->filing_id), 'handle']);
    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    expect(RawFact::query()->where('filing_id', $filing->filing_id)->firstOrFail()->getRawOriginal())->toBe($rawBefore)
        ->and(NormalizedFact::query()->where('filing_id', $filing->filing_id)->count())->toBe(2)
        ->and(NormalizedFact::query()->where('filing_id', $filing->filing_id)->where('normalization_version', '1.0.0@1')->exists())->toBeTrue()
        ->and(NormalizedFact::query()->where('filing_id', $filing->filing_id)->where('normalization_version', '1.0.0@2')->exists())->toBeTrue()
        ->and(NormalizedFact::query()->where('filing_id', $filing->filing_id)->where('normalization_version', '1.0.0@1')->firstOrFail()->getRawOriginal())->toBe($normalizedBefore)
        ->and(ValidationResult::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(ValidationResult::query()->where('filing_id', $filing->filing_id)->firstOrFail()->normalized_dataset_version)->not->toBeNull();
});

it('reprocesses validation while retaining the prior validation result', function () {
    Queue::fake();
    $filing = seedApprovedNormalizedFiling('FIL-E2E-VALIDATION-REPROCESS');
    ValidationRuleModel::query()->create([
        'rule_code' => 'E2E-RULE',
        'description' => 'Initial validation rule.',
        'severity' => 'INFO',
        'inputs' => ['canonical_concept' => 'total_assets'],
        'enabled' => true,
        'rule_version' => 1,
    ]);
    config(['financial-pipeline.validation.rule_set_version' => 'rules-1']);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'E2E-RULE' => approvedRule('E2E-RULE', 'PASS', 'INFO', '1'),
    ]));
    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);
    $oldResult = ValidationResult::query()->where('filing_id', $filing->filing_id)->firstOrFail();

    ValidationRuleModel::query()->where('rule_code', 'E2E-RULE')->update(['enabled' => false]);
    ValidationRuleModel::query()->create([
        'rule_code' => 'E2E-RULE',
        'description' => 'Updated validation rule.',
        'severity' => 'INFO',
        'inputs' => ['canonical_concept' => 'total_assets'],
        'enabled' => true,
        'rule_version' => 2,
    ]);
    config(['financial-pipeline.validation.rule_set_version' => 'rules-2']);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'E2E-RULE' => approvedRule('E2E-RULE', 'PASS', 'INFO', '2'),
    ]));

    app(ReprocessService::class)->start($filing->filing_id, ReprocessStage::Validate, 'Validation rule updated.', 'operator-2');
    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    expect(ValidationResult::query()->where('filing_id', $filing->filing_id)->count())->toBe(2)
        ->and(ValidationResult::query()->whereKey($oldResult->validation_result_id)->exists())->toBeTrue()
        ->and(ValidationResult::query()->where('filing_id', $filing->filing_id)->pluck('validation_rule_set_version')->sort()->values()->all())->toBe(['rules-1', 'rules-2']);
});

it('runs the full reprocess chain from parse through publish', function () {
    Queue::fake();
    Storage::fake('local');
    $filingId = 'FIL-E2E-REPROCESS-CHAIN';
    $source = 'approved-reprocess-source';
    configureApprovedDiscovery($filingId);
    Http::fake(['https://example.test/*' => Http::response($source, 200, ['Content-Type' => 'application/zip'])]);
    Process::fake(fn () => Process::result(json_encode(approvedParserPayload($filingId, hash('sha256', $source)), JSON_THROW_ON_ERROR), '', 0));
    createApprovedMappingAndRule();
    runApprovedPipeline($filingId);

    $filing = Filing::query()->findOrFail($filingId);
    $artifactBefore = Storage::disk('local')->get($filing->storage_path);
    $rawBefore = RawFact::query()->where('filing_id', $filingId)->firstOrFail()->getRawOriginal();

    app(ReprocessService::class)->start($filingId, ReprocessStage::Parse, 'Parser rerun verification.', 'operator-26');
    app()->call([new ParseXbrlJob($filingId), 'handle']);
    app()->call([new NormalizeFactsJob($filingId), 'handle']);
    app()->call([new ValidateFilingJob($filingId), 'handle']);
    app()->call([new PublishFilingJob($filingId), 'handle']);

    expect($filing->fresh()->processing_stage)->toBe(PipelineStage::Published->value)
        ->and($filing->fresh()->quality_status)->toBe('VERIFIED')
        ->and(Storage::disk('local')->get($filing->storage_path))->toBe($artifactBefore)
        ->and(RawFact::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(RawFact::query()->where('filing_id', $filingId)->firstOrFail()->getRawOriginal())->toBe($rawBefore)
        ->and(NormalizedFact::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(ValidationResult::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(PublishedSnapshot::query()->where('filing_id', $filingId)->count())->toBe(2);
});

function configureApprovedDiscovery(string $filingId): void
{
    config([
        'financial-pipeline.discovery.disk' => 'local',
        'financial-pipeline.discovery.fixture_path' => 'financial-pipeline/discovery/e2e-approved.json',
    ]);
    Storage::disk('local')->put('financial-pipeline/discovery/e2e-approved.json', json_encode([[
        'filing_id' => $filingId,
        'issuer_code' => 'TEST',
        'report_type' => 'ANNUAL',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_start' => null,
        'period_end' => '2025-12-31',
        'source_url' => "https://example.test/filings/{$filingId}.xbrl",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:approved',
        'revision_number' => 1,
        'discovered_at' => '2026-09-07T10:00:00+07:00',
    ]], JSON_THROW_ON_ERROR));
}

function runApprovedPipeline(string $filingId): void
{
    app()->call([new DiscoverFilingsJob(new DiscoveryCriteria(sourceAdapter: 'configured', discoveryWindow: '2026-Q3', pageSize: 10)), 'handle']);
    app()->call([new DownloadFilingJob($filingId), 'handle']);
    app()->call([new ParseXbrlJob($filingId), 'handle']);
    app()->call([new NormalizeFactsJob($filingId), 'handle']);
    app()->call([new ValidateFilingJob($filingId), 'handle']);
    app()->call([new PublishFilingJob($filingId), 'handle']);
}

/** @return array<string, int> */
function approvedPipelineCounts(string $filingId): array
{
    return [
        'filings' => Filing::query()->where('filing_id', $filingId)->count(),
        'artifacts' => FilingArtifact::query()->where('filing_id', $filingId)->count(),
        'raw' => RawFact::query()->where('filing_id', $filingId)->count(),
        'normalized' => NormalizedFact::query()->where('filing_id', $filingId)->count(),
        'validation' => ValidationResult::query()->where('filing_id', $filingId)->count(),
        'snapshots' => PublishedSnapshot::query()->where('filing_id', $filingId)->count(),
    ];
}

function createApprovedMappingAndRule(): void
{
    CanonicalConcept::query()->create([
        'code' => 'total_assets',
        'name' => 'Total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-E2E-1',
        'source_concept' => 'Assets',
        'canonical_concept' => 'total_assets',
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    ValidationRuleModel::query()->create([
        'rule_code' => 'E2E-RULE',
        'description' => 'Approved sample fact is present.',
        'severity' => 'INFO',
        'inputs' => ['canonical_concept' => 'total_assets'],
        'enabled' => true,
        'rule_version' => 1,
    ]);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'E2E-RULE' => approvedRule('E2E-RULE', 'PASS', 'INFO'),
    ]));
}

function seedApprovedNormalizedFiling(string $filingId): Filing
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
        'processing_stage' => PipelineStage::Validating->value,
        'quality_status' => 'PENDING',
    ]);
    config(['financial-pipeline.normalization.mapping_version' => '1']);
    CanonicalConcept::query()->firstOrCreate(['code' => 'total_assets'], [
        'name' => 'Total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);
    ConceptMapping::query()->firstOrCreate(['mapping_rule_id' => 'MAP-E2E-SEED-'.$filingId], [
        'source_concept' => 'Assets',
        'canonical_concept' => 'total_assets',
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-E2E-'.$filingId,
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
        'raw_fact_id' => 'RAW-E2E-'.$filingId,
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
    NormalizedFact::query()->create([
        'normalized_fact_id' => 'NF-E2E-'.$filingId,
        'filing_id' => $filingId,
        'raw_fact_id' => $raw->raw_fact_id,
        'issuer_code' => 'TEST',
        'period' => 'FY',
        'period_end' => '2025-12-31',
        'canonical_concept' => 'total_assets',
        'value' => '100',
        'currency' => 'IDR',
        'scope' => 'CONSOLIDATED',
        'data_type' => 'REPORTED',
        'source_concept' => 'Assets',
        'mapping_rule_id' => 'MAP-E2E-SEED-'.$filingId,
        'mapping_rule_version' => 1,
        'normalization_version' => '1.0.0@1',
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'PENDING',
    ]);
    PipelineRun::query()->create([
        'filing_id' => $filingId,
        'trigger' => 'NORMALIZE',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);

    return $filing;
}

/** @return array<string, mixed> */
function approvedParserPayload(string $filingId, string $sourceHash): array
{
    return [
        'parser_contract' => 'xbrl_parser_result',
        'parser_contract_version' => '1.0.0',
        'status' => 'SUCCESS',
        'filing_id' => $filingId,
        'source' => ['path' => 'approved.xbrl', 'sha256' => $sourceHash],
        'runtime' => ['worker_version' => '1.0.0', 'arelle_version' => '2.44.4', 'python_version' => '3.12.4'],
        'counts' => ['contexts' => 1, 'units' => 1, 'dimensions' => 0, 'facts' => 1],
        'contexts' => [[
            'context_id' => 'ctx-source-1', 'filing_id' => $filingId, 'source_context_id' => 'c1',
            'entity_identifier' => 'TEST', 'scope' => 'CONSOLIDATED', 'period_type' => 'INSTANT',
            'instant_date' => '2025-12-31', 'start_date' => null, 'end_date' => null, 'context_status' => 'RESOLVED',
        ]],
        'units' => [[
            'unit_id' => 'unit-source-1', 'filing_id' => $filingId, 'source_unit_id' => 'u1',
            'unit_type' => 'CURRENCY', 'measure' => 'iso4217:IDR', 'currency' => 'IDR',
        ]],
        'dimensions' => [],
        'facts' => [[
            'raw_fact_id' => 'fact-source-1', 'filing_id' => $filingId, 'source_concept' => 'Assets',
            'source_namespace' => 'http://example.com/ex', 'raw_value' => '100', 'normalized_numeric_value' => '100',
            'context_ref' => 'ctx-source-1', 'unit_ref' => 'unit-source-1', 'decimals' => '0', 'precision' => null,
            'is_nil' => false, 'fact_status' => 'EXTRACTED',
        ]],
        'warnings' => [],
        'errors' => [],
    ];
}

function approvedRule(string $code, string $result, string $severity, string $version = '1'): ValidationRule
{
    return new class($code, $result, $severity, $version) implements ValidationRule
    {
        public function __construct(private readonly string $codeValue, private readonly string $resultValue, private readonly string $severityValue, private readonly string $versionValue) {}

        public function code(): string
        {
            return $this->codeValue;
        }

        public function version(): string
        {
            return $this->versionValue;
        }

        public function appliesTo(FilingValidationContext $context): bool
        {
            return $context->normalizedFacts !== [];
        }

        public function evaluate(FilingValidationContext $context): RuleResult
        {
            return new RuleResult(
                result: $this->resultValue,
                severity: $this->severityValue,
                message: 'E2E quality result.',
                normalizedFactIds: array_map(
                    fn (NormalizedFact $normalizedFact): string => (string) $normalizedFact->normalized_fact_id,
                    $context->normalizedFacts,
                ),
            );
        }
    };
}
