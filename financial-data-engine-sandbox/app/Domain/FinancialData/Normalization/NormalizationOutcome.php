<?php

namespace App\Domain\FinancialData\Normalization;

final readonly class NormalizationOutcome
{
    public function __construct(
        public string $status,
        public ?string $canonicalConcept,
        public ?string $value,
        public ?string $scope,
        public ?string $period,
        public ?string $periodStart,
        public ?string $periodEnd,
        public ?string $currency,
        public string $sourceConcept,
        public ?string $mappingRuleId,
        public ?int $mappingRuleVersion,
        public ?string $reason = null,
        public string $exceptionRuleVersion = 'NONE',
    ) {}

    public function isNormalized(): bool
    {
        return $this->status === 'NORMALIZED';
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['UNMAPPED', 'REVIEW_REQUIRED'], true);
    }
}
