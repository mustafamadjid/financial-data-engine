<?php

use App\Domain\FinancialData\Pipeline\ReprocessStage;

it('supports exactly the five per filing reprocess operations', function () {
    expect(array_column(ReprocessStage::cases(), 'value'))->toBe([
        'DOWNLOAD', 'PARSE', 'NORMALIZE', 'VALIDATE', 'PUBLISH',
    ]);
});

it('rejects discovery, processing states and invalid reprocess input', function (string $value) {
    expect(ReprocessStage::tryFrom($value))->toBeNull();
    expect(fn () => ReprocessStage::from($value))->toThrow(ValueError::class);
})->with(['DISCOVER', 'DISCOVERY', 'DISCOVERED', 'PARSING', 'FAILED', '', 'parse', ' PARSE ']);
