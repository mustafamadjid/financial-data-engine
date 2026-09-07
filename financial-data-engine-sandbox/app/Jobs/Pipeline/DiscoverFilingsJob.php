<?php

namespace App\Jobs\Pipeline;

use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Domain\FinancialData\Discovery\FilingDiscoveryService;
use App\Domain\FinancialData\Pipeline\Contracts\FilingDiscoverySource;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Middleware\PipelineOverlapMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class DiscoverFilingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public readonly DiscoveryCriteria $criteria)
    {
        $this->onQueue((string) config('financial-pipeline.stages.DISCOVER.queue', 'filing-discovery'));
        $this->tries = (int) config('financial-pipeline.stages.DISCOVER.tries', 3);
        $this->timeout = (int) config('financial-pipeline.stages.DISCOVER.timeout', 120);
    }

    public function backoff(): array
    {
        return array_values((array) config('financial-pipeline.stages.DISCOVER.backoff', [30, 120]));
    }

    /**
     * @return list<PipelineOverlapMiddleware>
     */
    public function middleware(): array
    {
        return [new PipelineOverlapMiddleware(
            "discovery:{$this->criteria->sourceAdapter}",
            PipelineStage::Discovered,
            $this->timeout,
        )];
    }

    public function handle(FilingDiscoverySource $source, FilingDiscoveryService $discoveryService): void
    {
        foreach ($source->discover($this->criteria) as $candidate) {
            try {
                $discoveryService->persist(
                    $candidate,
                    $this->criteria->sourceAdapter,
                    $this->criteria->discoveryWindow,
                );
            } catch (InvalidArgumentException $exception) {
                Log::warning('pipeline.discovery.candidate_rejected', [
                    'source_adapter' => $this->criteria->sourceAdapter,
                    'candidate_id' => $candidate->filingId,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('pipeline.discovery.failed', [
            'source_adapter' => $this->criteria->sourceAdapter,
            'discovery_window' => $this->criteria->discoveryWindow,
            'error_type' => $exception::class,
            'error_message' => 'Discovery provider execution failed.',
        ]);
    }
}
