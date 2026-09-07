<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Validation\Contracts\ValidationRule;

final readonly class ConfiguredValidationRule implements ValidationRule
{
    public function __construct(
        private ValidationRule $implementation,
        private ValidationRuleDefinition $definition,
    ) {}

    public function code(): string
    {
        return $this->definition->code;
    }

    public function version(): string
    {
        return (string) $this->definition->version;
    }

    public function appliesTo(FilingValidationContext $context): bool
    {
        return $this->implementation->appliesTo($context->withRuleDefinition($this->definition));
    }

    public function evaluate(FilingValidationContext $context): RuleResult
    {
        $result = $this->implementation->evaluate($context->withRuleDefinition($this->definition));

        return new RuleResult(
            result: $result->result,
            severity: $this->definition->severity,
            message: $result->message,
            expectedValue: $result->expectedValue,
            actualValue: $result->actualValue,
            tolerance: $result->tolerance,
            normalizedFactIds: $result->normalizedFactIds,
        );
    }
}
