<?php

use App\Domain\FinancialData\Pipeline\QualityStatus;

it('uses the existing contract review vocabulary including pending', function () {
    $contract = json_decode(file_get_contents(__DIR__.'/../../../../../../contracts/v1/status-enums.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(array_column(QualityStatus::cases(), 'value'))->toBe($contract['statuses']['review']);
    expect(QualityStatus::from('VERIFIED'))->toBe(QualityStatus::Verified);
    expect(QualityStatus::from('REVIEW_REQUIRED'))->toBe(QualityStatus::ReviewRequired);
    expect(QualityStatus::from('FAILED'))->toBe(QualityStatus::Failed);
    expect(QualityStatus::from('PENDING'))->toBe(QualityStatus::Pending);
});

it('rejects processing and invalid values as quality statuses', function (string $value) {
    expect(QualityStatus::tryFrom($value))->toBeNull();
    expect(fn () => QualityStatus::from($value))->toThrow(ValueError::class);
})->with(['VALIDATED', 'PUBLISHED', 'NORMALIZED', '', 'verified', ' VERIFIED ', 'NULL']);
