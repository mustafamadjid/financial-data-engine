<?php

namespace App\Domain\FinancialData\Pipeline;

enum ReprocessStage: string
{
    case Download = 'DOWNLOAD';
    case Parse = 'PARSE';
    case Normalize = 'NORMALIZE';
    case Validate = 'VALIDATE';
    case Publish = 'PUBLISH';
}
