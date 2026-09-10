<?php

return [
    'contract' => env('PUBLIC_API_CONTRACT', 'hissa.financial-data.integration'),
    'contract_version' => env('PUBLIC_API_CONTRACT_VERSION', '0.1.0'),
    'rate_limit_per_minute' => (int) env('PUBLIC_API_RATE_LIMIT_PER_MINUTE', 60),
];
