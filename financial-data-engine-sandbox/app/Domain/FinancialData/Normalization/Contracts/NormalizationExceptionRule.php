<?php

namespace App\Domain\FinancialData\Normalization\Contracts;

use App\Models\ConceptMapping;
use App\Models\RawFact;

interface NormalizationExceptionRule
{
    public function code(): string;

    public function version(): string;

    public function appliesTo(RawFact $rawFact, ConceptMapping $mapping): bool;

    public function transform(string $value): string;
}
