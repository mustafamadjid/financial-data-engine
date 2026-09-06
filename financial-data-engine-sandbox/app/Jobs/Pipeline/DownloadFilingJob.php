<?php

namespace App\Jobs\Pipeline;

final class DownloadFilingJob extends PipelineJob
{
    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.DOWNLOAD.queue', 'filing-download'));
    }
}
