<?php

namespace App\Domain\FinancialData\Mapping;

final class MappingSeriesKey
{
    public static function from(string $sourceConcept, ?string $entryPoint, ?string $sourceNamespace = null): string
    {
        if ($sourceNamespace === null || trim($sourceNamespace) === '') {
            return substr(hash('sha256', trim($sourceConcept).'|'.trim((string) $entryPoint)), 0, 64);
        }

        return substr(hash('sha256', implode('|', [
            trim((string) $sourceNamespace),
            trim($sourceConcept),
            trim((string) $entryPoint),
        ])), 0, 64);
    }
}
