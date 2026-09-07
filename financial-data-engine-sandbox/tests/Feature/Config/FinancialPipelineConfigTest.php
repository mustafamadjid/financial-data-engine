<?php

use App\Domain\FinancialData\Pipeline\ReprocessStage;
use Illuminate\Support\Env;

it('loads a separate queue and retry policy for every executable stage', function (string $stage, string $queue, int $tries, int $timeout, array $backoff) {
    expect(config("financial-pipeline.stages.$stage"))->toBe([
        'queue' => $queue, 'tries' => $tries, 'timeout' => $timeout, 'backoff' => $backoff,
    ]);
})->with([
    ['DISCOVER', 'filing-discovery', 3, 120, [30, 120]],
    ['DOWNLOAD', 'filing-download', 5, 300, [30, 120, 300]],
    ['PARSE', 'filing-parse', 2, 900, [60]],
    ['NORMALIZE', 'filing-normalize', 3, 300, [30, 120]],
    ['VALIDATE', 'filing-validate', 3, 300, [30, 120]],
    ['PUBLISH', 'filing-publish', 3, 120, [30, 120]],
]);

it('configures every reprocess operation without treating terminal states as jobs', function () {
    $stages = config('financial-pipeline.stages');

    foreach (ReprocessStage::cases() as $stage) {
        expect($stages[$stage->value]['queue'])->toBeString()->not->toBeEmpty();
    }

    expect(array_keys($stages))->toBe(['DISCOVER', 'DOWNLOAD', 'PARSE', 'NORMALIZE', 'VALIDATE', 'PUBLISH']);
    expect(array_unique(array_column($stages, 'queue')))->toHaveCount(6);
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
            'queue' => 'sandbox-parser', 'tries' => 4, 'timeout' => 1200, 'backoff' => [60],
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
