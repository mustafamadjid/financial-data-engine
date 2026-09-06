<?php

namespace App\Jobs\Pipeline;

final class ValidateFilingJob extends PipelineJob
{
    public function __construct(string $filingId)
    {
        parent::__construct($filingId);
        $this->onQueue((string) config('financial-pipeline.stages.VALIDATE.queue', 'filing-validate'));
    }
}
