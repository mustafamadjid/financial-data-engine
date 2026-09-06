<?php

namespace App\Jobs\Pipeline;

final class NormalizeFactsJob extends PipelineJob
{
    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.NORMALIZE.queue', 'filing-normalize'));
    }
}
