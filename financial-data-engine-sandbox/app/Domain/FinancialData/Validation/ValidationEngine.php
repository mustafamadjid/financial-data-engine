<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Validation\Contracts\ValidationRule;
use App\Domain\FinancialData\Validation\Contracts\ValidationRuleProvider;
use InvalidArgumentException;

final class ValidationEngine
{
    public function __construct(private readonly ValidationRuleProvider $ruleProvider) {}

    /**
     * @return list<ValidationEvaluation>
     */
    public function evaluate(FilingValidationContext $context): array
    {
        $evaluations = [];

        foreach ($this->ruleProvider->applicable($context) as $rule) {
            if (! $rule instanceof ValidationRule || ! $rule->appliesTo($context)) {
                continue;
            }

            $result = $rule->evaluate($context);

            if (! $result instanceof RuleResult) {
                throw new InvalidArgumentException('Validation rules must return a RuleResult.');
            }

            $version = trim($rule->version());

            if (preg_match('/\A\d+\z/', $version) !== 1 || (int) $version < 1) {
                throw new InvalidArgumentException('Validation rule versions must be positive integers.');
            }

            $evaluations[] = new ValidationEvaluation($rule->code(), (int) $version, $result);
        }

        return $evaluations;
    }
}
