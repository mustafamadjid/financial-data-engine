<?php

namespace App\Domain\FinancialData\Validation;

use InvalidArgumentException;

final readonly class ValidationEvaluation
{
    public function __construct(
        public string $ruleCode,
        public int $ruleVersion,
        public RuleResult $result,
    ) {
        if (trim($ruleCode) === '' || $ruleVersion < 1) {
            throw new InvalidArgumentException('A valid validation rule identity is required.');
        }
    }
}
