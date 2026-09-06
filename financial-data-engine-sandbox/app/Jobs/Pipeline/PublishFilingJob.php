<?php

namespace App\Jobs\Pipeline;

final class PublishFilingJob extends PipelineJob
{
    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.PUBLISH.queue', 'filing-publish'));
    }
}
