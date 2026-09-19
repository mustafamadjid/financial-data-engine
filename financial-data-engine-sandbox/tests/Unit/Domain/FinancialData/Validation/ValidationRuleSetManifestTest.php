<?php

use App\Domain\FinancialData\Validation\Exceptions\TerminalValidationException;
use App\Domain\FinancialData\Validation\ValidationRuleSetManifest;
use Tests\TestCase;

uses(TestCase::class);

it('rejects an incomplete mandatory DA rule set', function () {
    expect(fn () => ValidationRuleSetManifest::assertComplete(['ACC-001']))
        ->toThrow(TerminalValidationException::class);
});

it('accepts the complete mandatory DA rule set', function () {
    ValidationRuleSetManifest::assertComplete(ValidationRuleSetManifest::mandatoryCodes());

    expect(true)->toBeTrue();
});
