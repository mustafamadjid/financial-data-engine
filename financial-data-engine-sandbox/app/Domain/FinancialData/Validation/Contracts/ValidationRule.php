<?php

namespace App\Domain\FinancialData\Validation\Contracts;

use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Domain\FinancialData\Validation\RuleResult;

interface ValidationRule
{
    public function code(): string;

    public function version(): string;

    public function appliesTo(FilingValidationContext $context): bool;

    public function evaluate(FilingValidationContext $context): RuleResult;
}
