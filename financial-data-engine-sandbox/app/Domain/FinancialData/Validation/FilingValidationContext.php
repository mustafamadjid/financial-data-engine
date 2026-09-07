<?php

namespace App\Domain\FinancialData\Validation;

use App\Models\Filing;
use App\Models\NormalizedFact;

final readonly class FilingValidationContext
{
    /**
     * @param  list<NormalizedFact>  $normalizedFacts
     * @param  list<array<string, mixed>>  $normalizationIssues
     */
    public function __construct(
        public Filing $filing,
        public array $normalizedFacts,
        public array $normalizationIssues = [],
        public ?ValidationRuleDefinition $ruleDefinition = null,
    ) {}

    public function withRuleDefinition(ValidationRuleDefinition $ruleDefinition): self
    {
        return new self(
            filing: $this->filing,
            normalizedFacts: $this->normalizedFacts,
            normalizationIssues: $this->normalizationIssues,
            ruleDefinition: $ruleDefinition,
        );
    }
}
