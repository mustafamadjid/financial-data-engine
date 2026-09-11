<?php

namespace App\Domain\FinancialData\Mapping;

final class MappingSeriesKey
{
    public static function from(string $sourceConcept, ?string $entryPoint): string
    {
        return substr(hash('sha256', trim($sourceConcept).'|'.trim((string) $entryPoint)), 0, 64);
    }
}
