<?php

namespace App\Jobs\Middleware;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class PipelineOverlapMiddleware extends WithoutOverlapping
{
    public function __construct(int|string $filingId, PipelineStage|string $stage, int $timeout)
    {
        $stageValue = $stage instanceof PipelineStage ? $stage->value : strtoupper($stage);
        $stageKey = match ($stageValue) {
            PipelineStage::Discovered->value => 'DISCOVER',
            PipelineStage::Downloading->value => 'DOWNLOAD',
            PipelineStage::Parsing->value => 'PARSE',
            PipelineStage::Normalizing->value => 'NORMALIZE',
            PipelineStage::Validating->value => 'VALIDATE',
            PipelineStage::Publishing->value => 'PUBLISH',
            default => null,
        };
        $connection = $stageKey === null
            ? null
            : config("financial-pipeline.stages.{$stageKey}.connection");
        $retryAfter = $connection === null
            ? 0
            : (int) config("queue.connections.{$connection}.retry_after", 0);

        parent::__construct("filing:{$filingId}:stage:{$stageValue}", expiresAfter: max($timeout + 30, $retryAfter));

        $this->shared();
    }
}
