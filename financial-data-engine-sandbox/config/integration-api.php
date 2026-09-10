<?php

return [
    'enabled' => (bool) env('HISSA_INTEGRATION_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
    'contract' => env('HISSA_INTEGRATION_CONTRACT', 'hissa.financial-data.integration'),
    'contract_version' => env('HISSA_INTEGRATION_CONTRACT_VERSION', '0.1.0'),
    'token' => env('HISSA_INTEGRATION_TOKEN'),
    'token_hash' => env('HISSA_INTEGRATION_TOKEN_HASH'),
    'identity_label' => env('HISSA_INTEGRATION_IDENTITY_LABEL', 'hissa-core-sandbox'),
    'rate_limit_per_minute' => (int) env('HISSA_INTEGRATION_RATE_LIMIT_PER_MINUTE', 60),
];
