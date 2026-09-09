<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Validation\Contracts\ValidationRule;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Domain\FinancialData\Validation\RuleResult;
use App\Domain\FinancialData\Validation\ValidationRuleRegistry;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\PipelineJobRun;
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
    config(['financial-pipeline.validation.rule_set_version' => null]);
});

it('persists individual validation results and publishes verified filings', function () {
    Queue::fake();
    $filing = makeValidateFiling('FIL-VALIDATE-1');
    makeValidateNormalizedFact($filing);
    makeValidateRule('BS-TEST', 1, 'INFO');
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'BS-TEST' => makeValidateRuleImplementation('BS-TEST', '1', new RuleResult(
            result: 'PASS',
            severity: 'INFO',
            message: 'Passed.',
            normalizedFactIds: ['NF-VALIDATE-1'],
        )),
    ]));

    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    $result = ValidationResult::query()->where('filing_id', $filing->filing_id)->first();

    expect($result)->not->toBeNull()
        ->and($result->rule_code)->toBe('BS-TEST')
        ->and($result->rule_version)->toBe(1)
        ->and($result->result)->toBe('PASS')
        ->and($result->normalized_fact_ids)->toBe(['NF-VALIDATE-1'])
        ->and($filing->fresh()->quality_status)->toBe('VERIFIED')
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Publishing->value)
        ->and(PipelineJobRun::query()->where('filing_id', $filing->filing_id)->where('stage', 'VALIDATING')->first()?->status)->toBe('SUCCEEDED');

    Queue::assertPushed(PublishFilingJob::class, fn (PublishFilingJob $job): bool => $job->filingId === $filing->filing_id);
});

it('stops automatic publishing for review and blocking outcomes', function (string $result, string $severity, string $qualityStatus) {
    Queue::fake();
    $filingId = 'FIL-VALIDATE-'.str_replace('_', '-', strtolower($qualityStatus));
    $filing = makeValidateFiling($filingId);
    makeValidateNormalizedFact($filing, 'NF-'.strtoupper($qualityStatus));
    makeValidateRule('RULE-'.$qualityStatus, 1, $severity);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'RULE-'.$qualityStatus => makeValidateRuleImplementation(
            'RULE-'.$qualityStatus,
            '1',
            new RuleResult($result, $severity, 'Quality outcome.'),
        ),
    ]));

    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    expect($filing->fresh()->quality_status)->toBe($qualityStatus)
        ->and($filing->fresh()->processing_stage)->toBe(PipelineStage::Validated->value)
        ->and(Queue::pushed(PublishFilingJob::class))->toHaveCount(0);
})->with([
    ['REVIEW_REQUIRED', 'WARN', 'REVIEW_REQUIRED'],
    ['FAIL', 'ERROR', 'FAILED'],
]);

it('does not duplicate results for the same normalized and rule versions', function () {
    Queue::fake();
    $filing = makeValidateFiling('FIL-VALIDATE-IDEMPOTENT');
    makeValidateNormalizedFact($filing);
    makeValidateRule('IDEMPOTENT-TEST', 1, 'INFO');
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'IDEMPOTENT-TEST' => makeValidateRuleImplementation('IDEMPOTENT-TEST', '1', new RuleResult('PASS', 'INFO')),
    ]));

    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);
    $resultBefore = ValidationResult::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray();
    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    expect(ValidationResult::query()->where('filing_id', $filing->filing_id)->count())->toBe(1)
        ->and(ValidationResult::query()->where('filing_id', $filing->filing_id)->firstOrFail()->toArray())->toBe($resultBefore)
        ->and(Queue::pushed(PublishFilingJob::class))->toHaveCount(1);
});

it('preserves validation history when the rule version changes', function () {
    Queue::fake();
    $filing = makeValidateFiling('FIL-VALIDATE-VERSION');
    makeValidateNormalizedFact($filing);
    $oldRule = makeValidateRule('VERSIONED-TEST', 1, 'INFO');
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'VERSIONED-TEST' => makeValidateRuleImplementation('VERSIONED-TEST', '1', new RuleResult('PASS', 'INFO')),
    ]));
    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    $oldRule->forceFill(['enabled' => false])->save();
    makeValidateRule('VERSIONED-TEST', 2, 'INFO');
    config(['financial-pipeline.validation.rule_set_version' => 2]);
    app()->instance(ValidationRuleRegistry::class, new ValidationRuleRegistry([
        'VERSIONED-TEST' => makeValidateRuleImplementation('VERSIONED-TEST', '2', new RuleResult('PASS', 'INFO')),
    ]));
    $filing->refresh()->forceFill(['processing_stage' => PipelineStage::Validating->value])->save();
    PipelineRun::query()->create([
        'filing_id' => $filing->filing_id,
        'trigger' => 'VALIDATE',
        'status' => 'RUNNING',
        'correlation_id' => (string) Str::uuid(),
        'started_at' => now(),
    ]);

    app()->call([new ValidateFilingJob($filing->filing_id), 'handle']);

    expect(ValidationResult::query()->where('filing_id', $filing->filing_id)->count())->toBe(2)
        ->and(ValidationResult::query()->where('filing_id', $filing->filing_id)->pluck('rule_version')->sort()->values()->all())->toBe([1, 2]);
});

it('uses the validation queue policy', function () {
    $job = new ValidateFilingJob('FIL-VALIDATE-CONFIG');

    expect($job->queue)->toBe('filing-validate')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300)
        ->and($job->backoff())->toBe([30, 120])
        ->and($job->middleware())->toHaveCount(1);
});

function makeValidateFiling(string $filingId): Filing
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
        'processing_stage' => PipelineStage::Validating->value,
        'quality_status' => 'PENDING',
    ]);
}

function makeValidateNormalizedFact(Filing $filing, string $normalizedFactId = 'NF-VALIDATE-1'): NormalizedFact
{
    $canonical = CanonicalConcept::query()->firstOrCreate(['code' => 'total_assets'], [
        'name' => 'Total assets',
        'statement' => 'BALANCE_SHEET',
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'allowed_scope' => ['CONSOLIDATED'],
    ]);
    $mapping = ConceptMapping::query()->create([
        'mapping_rule_id' => 'MAP-'.str_replace('FIL-', '', $filing->filing_id),
        'source_concept' => 'Assets',
        'canonical_concept' => $canonical->code,
        'allowed_scope' => ['CONSOLIDATED'],
        'period_type' => 'INSTANT',
        'sign_convention' => 'AS_REPORTED',
        'status' => 'APPROVED',
        'rule_version' => 1,
    ]);
    $context = XbrlContext::query()->create([
        'context_id' => 'CTX-'.str_replace('FIL-', '', $filing->filing_id),
        'filing_id' => $filing->filing_id,
        'source_context_id' => 'SRC-'.str_replace('FIL-', '', $filing->filing_id),
        'entity_identifier' => $filing->issuer_code,
        'scope' => 'CONSOLIDATED',
        'period_type' => 'INSTANT',
        'instant_date' => '2026-06-30',
        'context_status' => 'RESOLVED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $unit = XbrlUnit::query()->create([
        'unit_id' => 'UNIT-'.str_replace('FIL-', '', $filing->filing_id),
        'filing_id' => $filing->filing_id,
        'source_unit_id' => 'IDR',
        'unit_type' => 'CURRENCY',
        'currency' => 'IDR',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);
    $raw = RawFact::query()->create([
        'raw_fact_id' => 'RAW-'.str_replace('FIL-', '', $filing->filing_id),
        'filing_id' => $filing->filing_id,
        'source_concept' => 'Assets',
        'raw_value' => '100',
        'normalized_numeric_value' => '100',
        'context_ref' => $context->context_id,
        'unit_ref' => $unit->unit_id,
        'is_nil' => false,
        'fact_status' => 'EXTRACTED',
        'parser_version' => '1.0.0',
        'parser_config_version' => '1.0.0',
    ]);

    return NormalizedFact::query()->create([
        'normalized_fact_id' => $normalizedFactId,
        'filing_id' => $filing->filing_id,
        'raw_fact_id' => $raw->raw_fact_id,
        'issuer_code' => $filing->issuer_code,
        'period' => $filing->fiscal_period,
        'period_end' => '2026-06-30',
        'canonical_concept' => $canonical->code,
        'value' => '100',
        'currency' => 'IDR',
        'scope' => 'CONSOLIDATED',
        'data_type' => 'REPORTED',
        'source_concept' => 'Assets',
        'mapping_rule_id' => $mapping->mapping_rule_id,
        'mapping_rule_version' => 1,
        'normalization_status' => 'NORMALIZED',
        'validation_status' => 'PENDING',
        'normalization_version' => '1.0.0',
    ]);
}

function makeValidateRule(string $ruleCode, int $version, string $severity): ValidationRuleModel
{
    return ValidationRuleModel::query()->create([
        'rule_code' => $ruleCode,
        'description' => 'Test validation rule.',
        'severity' => $severity,
        'inputs' => ['canonical_concept' => 'total_assets'],
        'tolerance' => null,
        'enabled' => true,
        'rule_version' => $version,
    ]);
}

function makeValidateRuleImplementation(string $code, string $version, RuleResult $result): ValidationRule
{
    return new class($code, $version, $result) implements ValidationRule
    {
        public function __construct(
            private readonly string $codeValue,
            private readonly string $versionValue,
            private readonly RuleResult $resultValue,
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
            return true;
        }

        public function evaluate(FilingValidationContext $context): RuleResult
        {
            return $this->resultValue;
        }
    };
}
