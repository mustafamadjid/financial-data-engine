<?php

use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Domain\FinancialData\Validation\Contracts\ValidationRule;
use App\Domain\FinancialData\Validation\Contracts\ValidationRuleProvider;
use App\Domain\FinancialData\Validation\FilingQualityAggregator;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Domain\FinancialData\Validation\RuleResult;
use App\Domain\FinancialData\Validation\ValidationEngine;
use App\Models\Filing;
use Tests\TestCase;

uses(TestCase::class);

function validationContext(): FilingValidationContext
{
    return new FilingValidationContext(
        new Filing(['filing_id' => 'FIL-VALIDATE-UNIT']),
        [],
    );
}

function fakeValidationRule(string $code, string $version, RuleResult $result): ValidationRule
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

it('executes only applicable rules and retains rule version and evidence', function () {
    $pass = fakeValidationRule('BS-001', '1', new RuleResult(
        result: 'PASS',
        severity: 'INFO',
        message: 'Balance sheet is balanced.',
        normalizedFactIds: ['NF-1'],
        expectedValue: '100',
        actualValue: '100',
        tolerance: '0',
    ));
    $notApplicable = new class implements ValidationRule
    {
        public function code(): string
        {
            return 'SKIP-001';
        }

        public function version(): string
        {
            return '3';
        }

        public function appliesTo(FilingValidationContext $context): bool
        {
            return false;
        }

        public function evaluate(FilingValidationContext $context): RuleResult
        {
            throw new RuntimeException('Not applicable rules must not execute.');
        }
    };
    $provider = new class([$pass, $notApplicable]) implements ValidationRuleProvider
    {
        public function __construct(private readonly array $rules) {}

        public function applicable(FilingValidationContext $context): iterable
        {
            return $this->rules;
        }
    };

    $evaluations = (new ValidationEngine($provider))->evaluate(validationContext());

    expect($evaluations)->toHaveCount(1)
        ->and($evaluations[0]->ruleCode)->toBe('BS-001')
        ->and($evaluations[0]->ruleVersion)->toBe(1)
        ->and($evaluations[0]->result->normalizedFactIds)->toBe(['NF-1']);
});

it('aggregates passing, review, and blocking results centrally', function () {
    $aggregator = new FilingQualityAggregator;

    expect($aggregator->aggregate([]))->toBe(QualityStatus::Verified)
        ->and($aggregator->aggregate([
            validationEvaluation('PASS', 'INFO'),
        ]))->toBe(QualityStatus::Verified)
        ->and($aggregator->aggregate([
            validationEvaluation('REVIEW_REQUIRED', 'WARN'),
        ]))->toBe(QualityStatus::ReviewRequired)
        ->and($aggregator->aggregate([
            validationEvaluation('FAIL', 'ERROR'),
        ]))->toBe(QualityStatus::Failed);
});

it('does not downgrade a normalization review condition', function () {
    $status = (new FilingQualityAggregator)->aggregate(
        [validationEvaluation('PASS', 'INFO')],
        QualityStatus::ReviewRequired,
    );

    expect($status)->toBe(QualityStatus::ReviewRequired);
});

function validationEvaluation(string $result, string $severity): object
{
    $rule = fakeValidationRule('RULE-001', '1', new RuleResult($result, $severity));

    return (new ValidationEngine(new class([$rule]) implements ValidationRuleProvider
    {
        public function __construct(private readonly array $rules) {}

        public function applicable(FilingValidationContext $context): iterable
        {
            return $this->rules;
        }
    }))->evaluate(validationContext())[0];
}
