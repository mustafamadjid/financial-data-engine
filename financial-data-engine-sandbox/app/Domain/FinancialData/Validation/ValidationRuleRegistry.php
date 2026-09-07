<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Validation\Contracts\ValidationRule;

final class ValidationRuleRegistry
{
    /**
     * @param  array<string, ValidationRule|class-string<ValidationRule>>  $implementations
     */
    public function __construct(private readonly array $implementations = []) {}

    public function resolve(string $code): ?ValidationRule
    {
        $implementation = $this->implementations[$code] ?? null;

        if ($implementation instanceof ValidationRule) {
            return $implementation;
        }

        if (is_string($implementation) && is_a($implementation, ValidationRule::class, true)) {
            return app($implementation);
        }

        return null;
    }
}
