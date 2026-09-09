<?php

use App\Domain\FinancialData\Pipeline\ReprocessStage;
use Illuminate\Support\Env;

it('loads a separate queue and retry policy for every executable stage', function (string $stage, string $connection, string $queue, int $tries, int $timeout, array $backoff) {
    expect(config("financial-pipeline.stages.$stage"))->toBe([
        'connection' => $connection, 'queue' => $queue, 'tries' => $tries,
        'timeout' => $timeout, 'backoff' => $backoff,
    ]);
})->with([
    ['DISCOVER', 'redis-discovery', 'discovery', 3, 120, [30, 120]],
    ['DOWNLOAD', 'redis-downloads', 'downloads', 5, 300, [30, 120, 300]],
    ['PARSE', 'redis-xbrl', 'xbrl', 2, 900, [60]],
    ['NORMALIZE', 'redis-normalize', 'normalize', 3, 300, [30, 120]],
    ['VALIDATE', 'redis-validate', 'validate', 3, 300, [30, 120]],
    ['PUBLISH', 'redis-publish', 'publish', 3, 120, [30, 120]],
]);

it('configures every reprocess operation without treating terminal states as jobs', function () {
    $stages = config('financial-pipeline.stages');

    foreach (ReprocessStage::cases() as $stage) {
        expect($stages[$stage->value]['queue'])->toBeString()->not->toBeEmpty();
    }

    expect(array_keys($stages))->toBe(['DISCOVER', 'DOWNLOAD', 'PARSE', 'NORMALIZE', 'VALIDATE', 'PUBLISH']);
    expect(array_unique(array_column($stages, 'queue')))->toHaveCount(6);
});

it('defines reserved workload profiles without activating them as pipeline stages', function () {
    $stages = config('financial-pipeline.stages');
    $reserved = config('financial-pipeline.reserved_workloads');

    expect($reserved)->toHaveKeys(['ANALYTICS', 'ENRICHMENT'])
        ->and($stages)->not->toHaveKeys(['ANALYTICS', 'ENRICHMENT'])
        ->and($reserved['ANALYTICS'])->toMatchArray([
            'connection' => 'redis-analytics', 'queue' => 'analytics', 'tries' => 3,
            'timeout' => 300, 'backoff' => [30, 120],
        ])
        ->and($reserved['ENRICHMENT'])->toMatchArray([
            'connection' => 'redis-enrichment', 'queue' => 'enrichment', 'tries' => 5,
            'timeout' => 180, 'backoff' => [30, 120, 300],
        ]);
});

it('keeps canonical queue names unique across active and reserved workloads', function () {
    $profiles = array_merge(
        config('financial-pipeline.stages'),
        config('financial-pipeline.reserved_workloads'),
    );

    expect(array_unique(array_column($profiles, 'queue')))->toHaveCount(count($profiles));
});

it('keeps every workload reservation window safely above its timeout', function () {
    $profiles = array_merge(
        config('financial-pipeline.stages'),
        config('financial-pipeline.reserved_workloads'),
    );

    foreach ($profiles as $profile) {
        $retryAfter = config("queue.connections.{$profile['connection']}.retry_after");

        expect($retryAfter)->toBeInt()->toBeGreaterThanOrEqual($profile['timeout'] + 30);
    }

    expect(config('financial-pipeline.stages.PARSE.timeout'))->toBe(900)
        ->and(config('queue.connections.redis-xbrl.retry_after'))->toBeGreaterThanOrEqual(930);
});

it('provides isolated baseline worker profiles and an explicit combined priority', function () {
    expect(config('financial-pipeline.worker_profiles'))->toBe([
        'discovery' => ['processes' => 1],
        'downloads' => ['processes' => 2],
        'xbrl' => ['processes' => 1],
        'normalize' => ['processes' => 2],
        'validate' => ['processes' => 2],
        'analytics' => ['processes' => 0],
        'enrichment' => ['processes' => 0],
        'publish' => ['processes' => 1],
    ])->and(config('financial-pipeline.combined_worker_priority'))->toBe([
        'publish', 'validate', 'normalize', 'xbrl',
        'downloads', 'discovery', 'analytics', 'enrichment',
    ]);
});

it('keeps Horizon out of the queue stack and documents Redis as the sandbox queue backend', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require'] ?? [])->not->toHaveKey('laravel/horizon')
        ->and($composer['require-dev'] ?? [])->not->toHaveKey('laravel/horizon')
        ->and(config('queue.connections.redis.driver'))->toBe('redis')
        ->and(file_get_contents(base_path('.env.example')))->toContain('QUEUE_CONNECTION=redis');
});

it('uses queue work instead of queue listen for the development worker', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $scripts = json_encode($composer['scripts'] ?? [], JSON_THROW_ON_ERROR);

    expect($scripts)->toContain('queue:work')
        ->not->toContain('queue:listen')
        ->not->toContain('--tries=1');
});

it('points at the existing parser module, versioned contracts and private storage', function () {
    $contract = json_decode(file_get_contents(base_path('../contracts/v1/status-enums.json')), true, flags: JSON_THROW_ON_ERROR);

    expect(config('financial-pipeline.contract_version'))->toBe($contract['version']);
    expect(config('financial-pipeline.parser.command'))->toBe(['python', '-m', 'hissa_xbrl_worker']);
    expect(realpath(config('financial-pipeline.parser.working_directory')))->toBe(realpath(base_path('../python/xbrl-worker')));
    expect(is_file(config('financial-pipeline.parser.working_directory').'/hissa_xbrl_worker/__main__.py'))->toBeTrue();
    expect(config('financial-pipeline.storage'))->toBe([
        'disk' => 'local', 'artifacts_path' => 'financial-pipeline/artifacts',
        'temporary_path' => 'financial-pipeline/tmp', 'published_path' => 'financial-pipeline/published',
    ]);
    expect(config('filesystems.disks.'.config('financial-pipeline.storage.disk').'.root'))->toBe(storage_path('app/private'));
});

it('allows deployment overrides and casts numeric job options', function () {
    $overrides = [
        'FINANCIAL_PIPELINE_PARSE_QUEUE' => 'sandbox-parser',
        'FINANCIAL_PIPELINE_PARSE_CONNECTION' => 'redis-sandbox',
        'FINANCIAL_PIPELINE_PARSE_TRIES' => '4',
        'FINANCIAL_PIPELINE_PARSE_TIMEOUT' => '1200',
        'FINANCIAL_PIPELINE_PARSER_EXECUTABLE' => 'C:/Python Sandbox/python.exe',
        'FINANCIAL_PIPELINE_PARSER_PATH' => 'C:/Parser Sandbox',
        'FINANCIAL_PIPELINE_CONTRACT_VERSION' => '2.0.0',
        'FINANCIAL_PIPELINE_STORAGE_DISK' => 'sandbox',
        'FINANCIAL_PIPELINE_ARTIFACTS_PATH' => 'custom/artifacts',
        'FINANCIAL_PIPELINE_TEMPORARY_PATH' => 'custom/tmp',
        'FINANCIAL_PIPELINE_PUBLISHED_PATH' => 'custom/published',
    ];
    $repository = Env::getRepository();
    $original = [];

    try {
        foreach ($overrides as $key => $value) {
            $original[$key] = $repository->get($key);
            $repository->set($key, $value);
        }

        $config = require config_path('financial-pipeline.php');

        expect($config['stages']['PARSE'])->toBe([
            'connection' => 'redis-sandbox', 'queue' => 'sandbox-parser', 'tries' => 4,
            'timeout' => 1200, 'backoff' => [60],
        ]);
        expect($config['parser'])->toBe([
            'command' => ['C:/Python Sandbox/python.exe', '-m', 'hissa_xbrl_worker'],
            'working_directory' => 'C:/Parser Sandbox',
            'version' => '1.0.0',
            'config_version' => '1.0.0',
            'process_timeout' => 900,
        ]);
        expect($config['contract_version'])->toBe('2.0.0');
        expect($config['storage'])->toBe([
            'disk' => 'sandbox', 'artifacts_path' => 'custom/artifacts',
            'temporary_path' => 'custom/tmp', 'published_path' => 'custom/published',
        ]);
    } finally {
        foreach ($original as $key => $value) {
            if ($value === null) {
                $repository->clear($key);
            } else {
                $repository->set($key, $value);
            }
        }
    }
});
