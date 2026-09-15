<?php

namespace App\Application\Ops\DebtReview;

use RuntimeException;

final class DebtReviewGateException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode = 'DEBT_REVIEW_DISABLED',
        string $message = 'Debt Review is not released.',
        public readonly int $status = 404,
    ) {
        parent::__construct($message);
    }
}
