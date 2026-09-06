<?php

return [
    'contract_version' => env('FINANCIAL_PIPELINE_CONTRACT_VERSION', '1.0.0'),

    /*
     * Keys identify executable operations, not completed/failed processing
     * states. ReprocessStage values address these same operation keys.
     * Timeouts and backoff values are in seconds. When workers are added,
     * the queue connection retry_after must exceed the longest job timeout.
     */
    'stages' => [
        'DISCOVER' => [
            'queue' => env('FINANCIAL_PIPELINE_DISCOVER_QUEUE', 'filing-discovery'),
            'tries' => (int) env('FINANCIAL_PIPELINE_DISCOVER_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_DISCOVER_TIMEOUT', 120),
            'backoff' => [30, 120],
        ],
        'DOWNLOAD' => [
            'queue' => env('FINANCIAL_PIPELINE_DOWNLOAD_QUEUE', 'filing-download'),
            'tries' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_TRIES', 5),
            'timeout' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_TIMEOUT', 300),
            'backoff' => [30, 120, 300],
        ],
        'PARSE' => [
            'queue' => env('FINANCIAL_PIPELINE_PARSE_QUEUE', 'filing-parse'),
            'tries' => (int) env('FINANCIAL_PIPELINE_PARSE_TRIES', 2),
            'timeout' => (int) env('FINANCIAL_PIPELINE_PARSE_TIMEOUT', 900),
            'backoff' => [60],
        ],
        'NORMALIZE' => [
            'queue' => env('FINANCIAL_PIPELINE_NORMALIZE_QUEUE', 'filing-normalize'),
            'tries' => (int) env('FINANCIAL_PIPELINE_NORMALIZE_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_NORMALIZE_TIMEOUT', 300),
            'backoff' => [30, 120],
        ],
        'VALIDATE' => [
            'queue' => env('FINANCIAL_PIPELINE_VALIDATE_QUEUE', 'filing-validate'),
            'tries' => (int) env('FINANCIAL_PIPELINE_VALIDATE_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_VALIDATE_TIMEOUT', 300),
            'backoff' => [30, 120],
        ],
        'PUBLISH' => [
            'queue' => env('FINANCIAL_PIPELINE_PUBLISH_QUEUE', 'filing-publish'),
            'tries' => (int) env('FINANCIAL_PIPELINE_PUBLISH_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_PUBLISH_TIMEOUT', 120),
            'backoff' => [30, 120],
        ],
    ],

    /*
     * Run in the Phase 4 worker directory so Python can resolve the module.
     * Set the executable to the worker virtual environment's Python path.
     * Keep command arguments separate so executable paths may contain spaces.
     */
    'parser' => [
        'command' => [env('FINANCIAL_PIPELINE_PARSER_EXECUTABLE', 'python'), '-m', 'hissa_xbrl_worker'],
        'working_directory' => env('FINANCIAL_PIPELINE_PARSER_PATH', base_path('../python/xbrl-worker')),
    ],

    /* Paths are relative to the selected Laravel storage disk. */
    'storage' => [
        'disk' => env('FINANCIAL_PIPELINE_STORAGE_DISK', 'local'),
        'artifacts_path' => env('FINANCIAL_PIPELINE_ARTIFACTS_PATH', 'financial-pipeline/artifacts'),
        'temporary_path' => env('FINANCIAL_PIPELINE_TEMPORARY_PATH', 'financial-pipeline/tmp'),
        'published_path' => env('FINANCIAL_PIPELINE_PUBLISHED_PATH', 'financial-pipeline/published'),
    ],
];
