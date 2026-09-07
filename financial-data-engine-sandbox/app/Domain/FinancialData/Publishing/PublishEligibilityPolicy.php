<?php

namespace App\Domain\FinancialData\Publishing;

use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Models\Filing;

final class PublishEligibilityPolicy
{
    public function check(Filing $filing): PublishEligibility
    {
        $qualityStatus = QualityStatus::tryFrom((string) $filing->quality_status) ?? QualityStatus::Pending;

        return match ($qualityStatus) {
            QualityStatus::Verified => new PublishEligibility(true, $qualityStatus, 'Filing quality is VERIFIED.'),
            QualityStatus::ReviewRequired => new PublishEligibility(false, $qualityStatus, 'Filing requires explicit review before publishing.'),
            QualityStatus::Failed => new PublishEligibility(false, $qualityStatus, 'Filing quality is FAILED and cannot be published.'),
            QualityStatus::Pending => new PublishEligibility(false, $qualityStatus, 'Filing quality has not been verified.'),
        };
    }
}
