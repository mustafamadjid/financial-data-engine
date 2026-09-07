<?php

namespace App\Application\Pipeline;

use App\Application\Pipeline\Exceptions\InvalidPipelineTransition;
use App\Domain\FinancialData\Pipeline\PipelineStage;
use App\Jobs\Pipeline\DownloadFilingJob;
use App\Jobs\Pipeline\NormalizeFactsJob;
use App\Jobs\Pipeline\ParseXbrlJob;
use App\Jobs\Pipeline\PublishFilingJob;
use App\Jobs\Pipeline\ValidateFilingJob;

final readonly class PipelineTransition
{
    /**
     * @param  class-string  $jobClass
     */
    public function __construct(
        public PipelineStage $nextStage,
        public string $jobClass,
        public string $queueName,
    ) {}

    public static function fromCompletedStage(PipelineStage $completedStage): self
    {
        $transition = match ($completedStage) {
            PipelineStage::Discovered => [PipelineStage::Downloading, DownloadFilingJob::class, 'DOWNLOAD'],
            PipelineStage::Downloaded => [PipelineStage::Parsing, ParseXbrlJob::class, 'PARSE'],
            PipelineStage::Parsed => [PipelineStage::Normalizing, NormalizeFactsJob::class, 'NORMALIZE'],
            PipelineStage::Normalized => [PipelineStage::Validating, ValidateFilingJob::class, 'VALIDATE'],
            PipelineStage::Validated => [PipelineStage::Publishing, PublishFilingJob::class, 'PUBLISH'],
            default => throw new InvalidPipelineTransition($completedStage, $completedStage),
        };

        return new self(
            nextStage: $transition[0],
            jobClass: $transition[1],
            queueName: (string) config("financial-pipeline.stages.{$transition[2]}.queue"),
        );
    }
}
