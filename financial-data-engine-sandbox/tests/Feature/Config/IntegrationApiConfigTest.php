<?php

it('keeps integration api disabled outside explicitly enabled environments', function (): void {
    expect(config('integration-api'))->toHaveKeys([
        'enabled', 'contract', 'contract_version', 'token', 'identity_label', 'rate_limit_per_minute',
    ]);
});
