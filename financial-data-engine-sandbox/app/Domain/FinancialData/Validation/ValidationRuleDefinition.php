<?php

namespace App\Domain\FinancialData\Validation;

use App\Models\ValidationRule as ValidationRuleModel;

final readonly class ValidationRuleDefinition
{
    /**
     * @param  array<string, mixed>|null  $inputs
     */
    public function __construct(
        public string $code,
        public int $version,
        public string $severity,
        public ?array $inputs,
        public ?string $tolerance,
    ) {}

    public static function fromModel(ValidationRuleModel $rule): self
    {
        return new self(
            code: (string) $rule->rule_code,
            version: (int) $rule->rule_version,
            severity: strtoupper((string) $rule->severity),
            inputs: $rule->inputs,
            tolerance: $rule->tolerance === null ? null : (string) $rule->tolerance,
        );
    }
}
