<?php

namespace App\Domain\FinancialData\Pipeline;

enum PipelineStage: string
{
    case Discovered = 'DISCOVERED';
    case Downloading = 'DOWNLOADING';
    case Downloaded = 'DOWNLOADED';
    case Parsing = 'PARSING';
    case Parsed = 'PARSED';
    case Normalizing = 'NORMALIZING';
    case Normalized = 'NORMALIZED';
    case Validating = 'VALIDATING';
    case Validated = 'VALIDATED';
    case Publishing = 'PUBLISHING';
    case Published = 'PUBLISHED';
    case Failed = 'FAILED';
}
