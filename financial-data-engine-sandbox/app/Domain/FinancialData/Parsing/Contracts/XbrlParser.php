<?php

namespace App\Domain\FinancialData\Parsing\Contracts;

use App\Domain\FinancialData\Parsing\ParsedFilingData;
use App\Domain\FinancialData\Parsing\ParserExecutionContext;

interface XbrlParser
{
    public function parse(string $artifactPath, ParserExecutionContext $context): ParsedFilingData;
}
