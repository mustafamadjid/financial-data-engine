<?php

namespace App\Domain\FinancialData\Validation\Contracts;

use App\Domain\FinancialData\Validation\FilingValidationContext;

interface ValidationRuleProvider
{
    /**
     * @return iterable<ValidationRule>
     */
    public function applicable(FilingValidationContext $context): iterable;
}
