<?php

namespace App\Jobs\Middleware;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class PipelineOverlapMiddleware extends WithoutOverlapping
{
    public function __construct(int|string $filingId, PipelineStage|string $stage, int $timeout)
    {
        $stageValue = $stage instanceof PipelineStage ? $stage->value : strtoupper($stage);

        parent::__construct("filing:{$filingId}:stage:{$stageValue}", expiresAfter: max($timeout * 2, $timeout + 60));

        $this->shared();
    }
}
