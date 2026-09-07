<?php

namespace App\Domain\FinancialData\Pipeline;

/**
 * The review vocabulary from contracts/v1/status-enums.json.
 * Pending represents quality that has not yet been evaluated.
 */
enum QualityStatus: string
{
    case Pending = 'PENDING';
    case Verified = 'VERIFIED';
    case ReviewRequired = 'REVIEW_REQUIRED';
    case Failed = 'FAILED';
}
