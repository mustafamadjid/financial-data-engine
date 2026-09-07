<?php

use App\Domain\FinancialData\Pipeline\QualityStatus;
use App\Domain\FinancialData\Publishing\PublishEligibilityPolicy;
use App\Models\Filing;

it('allows only verified filings for automatic publish', function (string $qualityStatus, bool $allowed) {
    $filing = new Filing(['quality_status' => $qualityStatus]);

    $decision = (new PublishEligibilityPolicy)->check($filing);

    expect($decision->allowed())->toBe($allowed)
        ->and($decision->status())->toBe(QualityStatus::from($qualityStatus))
        ->and($decision->reason())->not->toBeEmpty();
})->with([
    ['VERIFIED', true],
    ['REVIEW_REQUIRED', false],
    ['FAILED', false],
]);
