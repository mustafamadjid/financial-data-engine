<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Domain\FinancialData\Pipeline\QualityStatus;

it('exposes the complete processing vocabulary', function () {
    expect(array_column(PipelineStage::cases(), 'value'))->toBe([
        'DISCOVERED', 'DOWNLOADING', 'DOWNLOADED', 'PARSING', 'PARSED',
        'NORMALIZING', 'NORMALIZED', 'VALIDATING', 'VALIDATED',
        'PUBLISHING', 'PUBLISHED', 'FAILED',
    ]);
});

it('rejects quality and invalid values as processing stages', function (string $value) {
    expect(PipelineStage::tryFrom($value))->toBeNull();
    expect(fn () => PipelineStage::from($value))->toThrow(ValueError::class);
})->with(['VERIFIED', 'REVIEW_REQUIRED', 'PENDING', '', 'parsed', ' PARSED ', 'DOWNLOAD']);

it('keeps validated processing and failed processing distinct from quality', function () {
    expect(PipelineStage::from('VALIDATED'))->toBe(PipelineStage::Validated);
    expect(PipelineStage::Failed)->not->toBe(QualityStatus::Failed);
});
