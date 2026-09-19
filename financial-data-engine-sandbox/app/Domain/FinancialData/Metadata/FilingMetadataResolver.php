<?php

namespace App\Domain\FinancialData\Metadata;

use App\Models\Filing;
use App\Models\RawFact;
use Illuminate\Support\Collection;

final class FilingMetadataResolver
{
    private const VERSION = '1.0.0';

    /**
     * @param  Collection<int, RawFact>  $rawFacts
     */
    public function resolve(Filing $filing, Collection $rawFacts): FilingMetadataResolution
    {
        $deiFacts = $rawFacts->filter(fn (RawFact $fact): bool => str_ends_with(
            strtolower((string) $fact->source_namespace),
            '/dei',
        ));

        $scopeFacts = $deiFacts->filter(fn (RawFact $fact): bool => (string) $fact->source_concept === 'WhetherTheFinancialStatementsAreOfAnIndividualEntityOrAGroupOfEntities');
        $scopeFact = $scopeFacts->first(fn (RawFact $fact): bool => $this->isCurrentYearInstant($fact)) ?? $scopeFacts->first();
        $scope = $this->scope((string) ($scopeFact?->raw_value ?? ''));

        $currencyFacts = $deiFacts->filter(fn (RawFact $fact): bool => (string) $fact->source_concept === 'DescriptionOfPresentationCurrency');
        $currencyFact = $currencyFacts->first(fn (RawFact $fact): bool => $this->isCurrentYearInstant($fact)) ?? $currencyFacts->first();
        $currency = $this->currency((string) ($currencyFact?->raw_value ?? ''));
        $unitCurrencies = $rawFacts->map(fn (RawFact $fact): ?string => $fact->unit?->currency)
            ->filter()
            ->unique()
            ->values();

        if ($currency !== null && $unitCurrencies->isNotEmpty() && $unitCurrencies->contains(fn (string $unit): bool => strtoupper($unit) !== $currency)) {
            return new FilingMetadataResolution(
                scope: $scope,
                presentationCurrency: $currency,
                status: 'REVIEW_REQUIRED',
                evidenceRawFactIds: $this->evidenceIds($scopeFact, $currencyFact),
                resolverVersion: self::VERSION,
                reason: 'Monetary fact currency conflicts with DEI presentation currency.',
            );
        }

        if ($scope === 'UNKNOWN') {
            return new FilingMetadataResolution(
                scope: $scope,
                presentationCurrency: $currency,
                status: 'REVIEW_REQUIRED',
                evidenceRawFactIds: $this->evidenceIds($scopeFact, $currencyFact),
                resolverVersion: self::VERSION,
                reason: 'DEI reporting scope is missing or unrecognized.',
            );
        }

        return new FilingMetadataResolution(
            scope: $scope,
            presentationCurrency: $currency,
            status: 'RESOLVED',
            evidenceRawFactIds: $this->evidenceIds($scopeFact, $currencyFact),
            resolverVersion: self::VERSION,
        );
    }

    private function isCurrentYearInstant(RawFact $fact): bool
    {
        return $fact->context?->source_context_id === 'CurrentYearInstant';
    }

    private function scope(string $value): string
    {
        $value = strtolower(trim($value));

        return match (true) {
            str_contains($value, 'group') || str_contains($value, 'grup') => 'CONSOLIDATED',
            str_contains($value, 'single') || str_contains($value, 'tunggal') || str_contains($value, 'individual') => 'PARENT',
            default => 'UNKNOWN',
        };
    }

    private function currency(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return match (true) {
            str_contains($value, 'USD'), str_contains($value, 'DOLLAR'), str_contains($value, 'AMERIKA') => 'USD',
            str_contains($value, 'IDR'), str_contains($value, 'RUPIAH') => 'IDR',
            default => null,
        };
    }

    /** @return list<string> */
    private function evidenceIds(?RawFact ...$facts): array
    {
        return collect($facts)->filter()->map(fn (RawFact $fact): string => (string) $fact->raw_fact_id)->values()->all();
    }
}
