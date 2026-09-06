<?php

namespace App\Application\Pipeline\Exceptions;

use App\Domain\FinancialData\Pipeline\PipelineStage;
use DomainException;

final class InvalidPipelineTransition extends DomainException
{
    public function __construct(PipelineStage $currentStage, PipelineStage $completedStage)
    {
        parent::__construct(sprintf(
            'Cannot complete pipeline stage [%s] while filing is at [%s].',
            $completedStage->value,
            $currentStage->value,
        ));
    }
}
