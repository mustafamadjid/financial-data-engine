<?php

namespace App\Domain\FinancialData\Publishing;

use App\Domain\FinancialData\Pipeline\QualityStatus;

final readonly class PublishEligibility
{
    public function __construct(
        private bool $isAllowed,
        private QualityStatus $qualityStatus,
        private string $decisionReason,
    ) {}

    public function allowed(): bool
    {
        return $this->isAllowed;
    }

    public function status(): QualityStatus
    {
        return $this->qualityStatus;
    }

    public function reason(): string
    {
        return $this->decisionReason;
    }
}
