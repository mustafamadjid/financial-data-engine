<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Validation\Contracts\ValidationRule;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Domain\FinancialData\Validation\RuleResult;
use App\Domain\FinancialData\Validation\ValidationRuleRegistry;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineRun;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\ValidationRule as ValidationRuleModel;
use App\Models\XbrlContext;
use App\Models\XbrlUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    config([
        'financial-pipeline.normalization.mapping_version' => null,
        'financial-pipeline.validation.rule_set_version' => null,
    ]);
});

it('completes the normalize and validate checkpoint with lineage and versioned reprocess history', function () {
    Queue::fake();
    $filingId = 'FIL-CHECKPOINT-3';
    checkpoint3SeedApprovedSample($filingId);

    checkpoint3CreateCanonicalAndMapping('total_assets', 'MAP-CHECKPOINT-V1', 1);
    $ruleV1 = ValidationRuleModel::query()->create([
        'rule_code' => 'CHECKPOINT-001',
        'description' => 'Approved sample fact is present.',
        'severity' => 'INFO',
        'inputs' => ['canonical_concept' => 'total_assets'],
        'enabled' => true,
        'rule_version' => 1,
    ]);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'CHECKPOINT-001' => checkpoint3Rule('CHECKPOINT-001', '1', 'Approved sample passed.'),
    ]));

    $rawBefore = RawFact::query()->where('filing_id', $filingId)->get()->toArray();
    app()->call([new NormalizeFactsJob($filingId), 'handle']);
    app()->call([new ValidateFilingJob($filingId), 'handle']);

    $filing = Filing::query()->findOrFail($filingId);
    $firstNormalized = NormalizedFact::query()->where('filing_id', $filingId)->firstOrFail();
    $firstValidation = ValidationResult::query()->where('filing_id', $filingId)->firstOrFail();
    $normalizedAudit = AuditLog::query()->where('filing_id', $filingId)->where('action', 'filing.normalized')->firstOrFail();
    $validatedAudit = AuditLog::query()->where('filing_id', $filingId)->where('action', 'filing.validated')->firstOrFail();

    expect($filing->processing_stage)->toBe(PipelineStage::Publishing->value)
        ->and($filing->quality_status)->toBe('VERIFIED')
        ->and($firstNormalized->raw_fact_id)->toBe($rawBefore[0]['raw_fact_id'])
        ->and($firstNormalized->mapping_rule_id)->toBe('MAP-CHECKPOINT-V1')
        ->and($firstValidation->rule_code)->toBe('CHECKPOINT-001')
        ->and($firstValidation->rule_version)->toBe(1)
        ->and($firstValidation->result)->toBe('PASS')
        ->and($firstValidation->message)->toBe('Approved sample passed.')
        ->and($firstValidation->normalized_fact_ids)->toBe([$firstNormalized->normalized_fact_id])
        ->and($normalizedAudit->new_value['normalized_count'])->toBe(1)
        ->and($validatedAudit->new_value['quality_status'])->toBe('VERIFIED')
        ->and($validatedAudit->new_value['results'])->toHaveCount(1);

    app()->call([new NormalizeFactsJob($filingId), 'handle']);
    app()->call([new ValidateFilingJob($filingId), 'handle']);

    expect(RawFact::query()->where('filing_id', $filingId)->get()->toArray())->toBe($rawBefore)
        ->and(NormalizedFact::query()->where('filing_id', $filingId)->count())->toBe(1)
        ->and(ValidationResult::query()->where('filing_id', $filingId)->count())->toBe(1);

    checkpoint3CreateCanonicalAndMapping('total_assets_v2', 'MAP-CHECKPOINT-V2', 2);
    config(['financial-pipeline.normalization.mapping_version' => 2]);
    checkpoint3StartRun($filingId, PipelineStage::Normalizing, 'NORMALIZE');
    app()->call([new NormalizeFactsJob($filingId), 'handle']);
    app()->call([new ValidateFilingJob($filingId), 'handle']);

    expect(RawFact::query()->where('filing_id', $filingId)->get()->toArray())->toBe($rawBefore)
        ->and(NormalizedFact::query()->where('filing_id', $filingId)->count())->toBe(2)
        ->and(NormalizedFact::query()->where('filing_id', $filingId)->pluck('mapping_rule_id')->sort()->values()->all())->toBe(['MAP-CHECKPOINT-V1', 'MAP-CHECKPOINT-V2'])
        ->and(ValidationResult::query()->where('filing_id', $filingId)->count())->toBe(2);

    $ruleV1->forceFill(['enabled' => false])->save();
    ValidationRuleModel::query()->create([
        'rule_code' => 'CHECKPOINT-001',
        'description' => 'Approved sample fact is present, version two.',
        'severity' => 'INFO',
        'inputs' => ['canonical_concept' => 'total_assets_v2'],
        'enabled' => true,
        'rule_version' => 2,
    ]);
    config(['financial-pipeline.validation.rule_set_version' => 2]);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'CHECKPOINT-001' => checkpoint3Rule('CHECKPOINT-001', '2', 'Approved sample passed with rule version two.'),
    ]));
    checkpoint3StartRun($filingId, PipelineStage::Validating, 'VALIDATE');
    app()->call([new ValidateFilingJob($filingId), 'handle']);

    expect(Filing::query()->findOrFail($filingId)->quality_status)->toBe('VERIFIED')
        ->and(ValidationResult::query()->where('filing_id', $filingId)->count())->toBe(3)
        ->and(ValidationResult::query()->where('filing_id', $filingId)->pluck('rule_version')->sort()->values()->all())->toBe([1, 1, 2])
        ->and(Queue::pushed(ValidateFilingJob::class))->toHaveCount(2)
        ->and(Queue::pushed(PublishFilingJob::class))->toHaveCount(3);
});

function checkpoint3SeedApprovedSample(string $filingId): void
{
    Filing::query()->create([
        'filing_id' => $filingId,
        'issuer_code' => 'TEST',
        'report_type' => 'QUARTERLY',
        'fiscal_year' => 2025,
        'fiscal_period' => 'FY',
        'period_start' => null,
        'period_end' => '2025-12-31',
        'source_url' => "https://example.test/filings/{$filingId}.zip",
        'source_type' => 'XBRL_INSTANCE',
        'source_hash' => 'sha256:checkpoint-3',
        'revision_number' => 1,
        'processing_stage' => PipelineStage::Normalizing->value,
        'quality_status' => 'PENDING',
    ]);
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-CHECKPOINT-3',
        'filing_id' => $filingId,
        'source_context_id' => 'c1',
        'entity_identifier' => 'TEST',
        'scope' => 'UNKNOWN',
        'period_type' => 'INSTANT',
        'instant_date' => '2025-12-31',
        'context_status' => 'RESOLVED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $unit = XbrlUnit::query()->create([
        'unit_id' => 'UNIT-CHECKPOINT-3',
        'filing_id' => $filingId,
        'source_unit_id' => 'u1',
        'unit_type' => 'CURRENCY',
        'measure' => 'iso4217:IDR',
        'currency' => 'IDR',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    RawFact::query()->create([
        'raw_fact_id' => 'RAW-CHECKPOINT-3',
        'filing_id' => $filingId,
        'source_concept' => 'Assets',
        'source_namespace' => 'http://example.com/ex',
        'raw_value' => '42800000000000',
        'normalized_numeric_value' => '42800000000000',
        'context_ref' => $context->context_id,
        'unit_ref' => $unit->unit_id,
        'decimals' => '-6',
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    PipelineRun::query()->create([
        'filing_id' => $filingId,
        'trigger' => 'PARSE',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);
}

function checkpoint3CreateCanonicalAndMapping(string $canonicalCode, string $mappingId, int $ruleVersion): void
{
    CanonicalConcept::query()->create([
        'code' => $canonicalCode,
        'name' => $canonicalCode,
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['UNKNOWN'],
    ]);
    ConceptMapping::query()->create([
        'mapping_rule_id' => $mappingId,
        'source_concept' => 'Assets',
        'canonical_concept' => $canonicalCode,
        'allowed_scope' => ['UNKNOWN'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => $ruleVersion,
    ]);
}

function checkpoint3Rule(string $code, string $version, string $message): ValidationRule
{
    return new class($code, $version, $message) implements ValidationRule
    {
        public function __construct(
            private readonly string $codeValue,
            private readonly string $versionValue,
            private readonly string $messageValue,
        ) {}

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
                result: 'PASS',
                severity: 'INFO',
                message: $this->messageValue,
                normalizedFactIds: array_map(
                    fn ($normalizedFact): string => (string) $normalizedFact->normalized_fact_id,
                    $context->normalizedFacts,
                ),
            );
        }
    };
}

function checkpoint3StartRun(string $filingId, PipelineStage $stage, string $trigger): void
{
    Filing::query()->whereKey($filingId)->update([
        'processing_stage' => $stage->value,
        'quality_status' => 'PENDING',
    ]);
    PipelineRun::query()->create([
        'filing_id' => $filingId,
        'trigger' => $trigger,
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);
}
