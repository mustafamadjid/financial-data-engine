<?php

use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

it('uses the shared filing and stage lock with an expiry beyond the stage timeout', function () {
    $middleware = new PipelineOverlapMiddleware(42, PipelineStage::Parsing, 900);

    expect($middleware->key)->toBe('filing:42:stage:PARSING')
        ->and($middleware->expiresAfter)->toBe(960)
        ->and($middleware->shareKey)->toBeTrue();
});

it('uses the configured reservation window for the parser timeout', function () {
    $middleware = new PipelineOverlapMiddleware(42, PipelineStage::Parsing, 900);

    expect($middleware->expiresAfter)
        ->toBeGreaterThanOrEqual(900 + 30)
        ->toBeGreaterThanOrEqual((int) config('queue.connections.redis-xbrl.retry_after'));
});

it('releases a concurrent execution instead of running its mutating callback', function () {
    Cache::flush();
    $middleware = new PipelineOverlapMiddleware(42, PipelineStage::Parsing, 10);
    $firstJob = new class
    {
        public bool $released = false;

        public function release(mixed $delay): void
        {
            $this->released = true;
        }
    };
    $secondJob = new class
    {
        public bool $released = false;

        public function release(mixed $delay): void
        {
            $this->released = true;
        }
    };
    $mutations = 0;

    $middleware->handle($firstJob, function () use ($middleware, $secondJob, &$mutations): void {
        $middleware->handle($secondJob, function () use (&$mutations): void {
            $mutations++;
        });
        $mutations++;
    });

    expect($mutations)->toBe(1)
        ->and($secondJob->released)->toBeTrue();
});
