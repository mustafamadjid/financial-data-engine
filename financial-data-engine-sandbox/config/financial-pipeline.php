<?php

return [
    'contract_version' => env('FINANCIAL_PIPELINE_CONTRACT_VERSION', '1.0.0'),

    'discovery' => [
        'disk' => env('FINANCIAL_PIPELINE_DISCOVERY_DISK', env('FINANCIAL_PIPELINE_STORAGE_DISK', 'local')),
        'fixture_path' => env('FINANCIAL_PIPELINE_DISCOVERY_FIXTURE_PATH', 'financial-pipeline/discovery/candidates.json'),
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', env('FINANCIAL_PIPELINE_DISCOVERY_ALLOWED_HOSTS', 'example.test'))))),
        'max_bytes' => (int) env('FINANCIAL_PIPELINE_DISCOVERY_MAX_BYTES', 5 * 1024 * 1024),
        'max_candidates' => (int) env('FINANCIAL_PIPELINE_DISCOVERY_MAX_CANDIDATES', 1000),
    ],

    /*
     * Keys identify executable operations, not completed/failed processing
     * states. ReprocessStage values address these same operation keys.
     * Timeouts and backoff values are in seconds. When workers are added,
     * the queue connection retry_after must exceed the longest job timeout.
     */
    'stages' => [
        'DISCOVER' => [
            'connection' => env('FINANCIAL_PIPELINE_DISCOVER_CONNECTION', 'redis-discovery'),
            'queue' => env('FINANCIAL_PIPELINE_DISCOVER_QUEUE', 'discovery'),
            'tries' => (int) env('FINANCIAL_PIPELINE_DISCOVER_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_DISCOVER_TIMEOUT', 120),
            'backoff' => [30, 120],
        ],
        'DOWNLOAD' => [
            'connection' => env('FINANCIAL_PIPELINE_DOWNLOAD_CONNECTION', 'redis-downloads'),
            'queue' => env('FINANCIAL_PIPELINE_DOWNLOAD_QUEUE', 'downloads'),
            'tries' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_TRIES', 5),
            'timeout' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_TIMEOUT', 300),
            'backoff' => [30, 120, 300],
        ],
        'PARSE' => [
            'connection' => env('FINANCIAL_PIPELINE_PARSE_CONNECTION', 'redis-xbrl'),
            'queue' => env('FINANCIAL_PIPELINE_PARSE_QUEUE', 'xbrl'),
            'tries' => (int) env('FINANCIAL_PIPELINE_PARSE_TRIES', 2),
            'timeout' => (int) env('FINANCIAL_PIPELINE_PARSE_TIMEOUT', 900),
            'backoff' => [60],
        ],
        'NORMALIZE' => [
            'connection' => env('FINANCIAL_PIPELINE_NORMALIZE_CONNECTION', 'redis-normalize'),
            'queue' => env('FINANCIAL_PIPELINE_NORMALIZE_QUEUE', 'normalize'),
            'tries' => (int) env('FINANCIAL_PIPELINE_NORMALIZE_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_NORMALIZE_TIMEOUT', 300),
            'backoff' => [30, 120],
        ],
        'VALIDATE' => [
            'connection' => env('FINANCIAL_PIPELINE_VALIDATE_CONNECTION', 'redis-validate'),
            'queue' => env('FINANCIAL_PIPELINE_VALIDATE_QUEUE', 'validate'),
            'tries' => (int) env('FINANCIAL_PIPELINE_VALIDATE_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_VALIDATE_TIMEOUT', 300),
            'backoff' => [30, 120],
        ],
        'PUBLISH' => [
            'connection' => env('FINANCIAL_PIPELINE_PUBLISH_CONNECTION', 'redis-publish'),
            'queue' => env('FINANCIAL_PIPELINE_PUBLISH_QUEUE', 'publish'),
            'tries' => (int) env('FINANCIAL_PIPELINE_PUBLISH_TRIES', 3),
            'timeout' => (int) env('FINANCIAL_PIPELINE_PUBLISH_TIMEOUT', 120),
            'backoff' => [30, 120],
        ],
    ],

    'reserved_workloads' => [
        'ANALYTICS' => [
            'connection' => 'redis-analytics',
            'queue' => 'analytics',
            'tries' => 3,
            'timeout' => 300,
            'backoff' => [30, 120],
            'processes' => 0,
        ],
        'ENRICHMENT' => [
            'connection' => 'redis-enrichment',
            'queue' => 'enrichment',
            'tries' => 5,
            'timeout' => 180,
            'backoff' => [30, 120, 300],
            'processes' => 0,
        ],
    ],

    'worker_profiles' => [
        'discovery' => ['processes' => 1],
        'downloads' => ['processes' => 2],
        'xbrl' => ['processes' => 1],
        'normalize' => ['processes' => 2],
        'validate' => ['processes' => 2],
        'analytics' => ['processes' => 0],
        'enrichment' => ['processes' => 0],
        'publish' => ['processes' => 1],
    ],

    'combined_worker_priority' => [
        'publish', 'validate', 'normalize', 'xbrl',
        'downloads', 'discovery', 'analytics', 'enrichment',
    ],

    /*
     * Run in the Phase 4 worker directory so Python can resolve the module.
     * Set the executable to the worker virtual environment's Python path.
     * Keep command arguments separate so executable paths may contain spaces.
     */
    'parser' => [
        'command' => [env('FINANCIAL_PIPELINE_PARSER_EXECUTABLE', 'python'), '-m', 'hissa_xbrl_worker'],
        'working_directory' => env('FINANCIAL_PIPELINE_PARSER_PATH', base_path('../python/xbrl-worker')),
        'version' => env('FINANCIAL_PIPELINE_PARSER_VERSION', '1.0.0'),
        'config_version' => env('FINANCIAL_PIPELINE_PARSER_CONFIG_VERSION', '1.0.0'),
        'process_timeout' => (int) env('FINANCIAL_PIPELINE_PARSER_PROCESS_TIMEOUT', 900),
    ],

    /* Paths are relative to the selected Laravel storage disk. */
    'storage' => [
        'disk' => env('FINANCIAL_PIPELINE_STORAGE_DISK', 'local'),
        'artifacts_path' => env('FINANCIAL_PIPELINE_ARTIFACTS_PATH', 'financial-pipeline/artifacts'),
        'temporary_path' => env('FINANCIAL_PIPELINE_TEMPORARY_PATH', 'financial-pipeline/tmp'),
        'published_path' => env('FINANCIAL_PIPELINE_PUBLISHED_PATH', 'financial-pipeline/published'),
    ],

    'normalization' => [
        'version' => env('FINANCIAL_PIPELINE_NORMALIZATION_VERSION', '1.0.0'),
        'mapping_version' => env('FINANCIAL_PIPELINE_MAPPING_VERSION'),
    ],

    'validation' => [
        'rule_set_version' => env('FINANCIAL_PIPELINE_VALIDATION_RULE_SET_VERSION'),
        'rules' => [],
    ],

    'publish' => [
        'contract' => env('FINANCIAL_PIPELINE_PUBLISH_CONTRACT', 'hissa.financial-data.publish'),
        'contract_version' => env('FINANCIAL_PIPELINE_PUBLISH_CONTRACT_VERSION', '1.0.0'),
    ],

    'download' => [
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', env('FINANCIAL_PIPELINE_DOWNLOAD_ALLOWED_HOSTS', 'example.test'))))),
        'http_timeout' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_CONNECT_TIMEOUT', 10),
        'max_bytes' => (int) env('FINANCIAL_PIPELINE_DOWNLOAD_MAX_BYTES', 50 * 1024 * 1024),
        'allowed_extensions' => ['zip', 'xml', 'xbrl', 'ixbrl', 'html', 'htm', 'xlsx', 'xls', 'pdf'],
        'allowed_content_types' => [
            'application/zip', 'application/octet-stream', 'application/xml', 'text/xml',
            'text/html', 'application/xhtml+xml', 'application/pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ],
    ],
];
