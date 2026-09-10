<?php

it('exposes only public api contract and throttling configuration', function (): void {
    expect(config('api'))->toHaveKeys([
        'contract', 'contract_version', 'rate_limit_per_minute',
    ]);
});
