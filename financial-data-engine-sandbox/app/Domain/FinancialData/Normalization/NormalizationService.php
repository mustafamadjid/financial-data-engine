<?php

namespace App\Domain\FinancialData\Normalization;

use App\Domain\FinancialData\Normalization\Contracts\NormalizationExceptionRule;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\RawFact;
use DateTimeInterface;
use InvalidArgumentException;

final class NormalizationService
{
    /**
     * @param  iterable<NormalizationExceptionRule>  $exceptionRules
     */
    public function __construct(private readonly iterable $exceptionRules = []) {}

    /**
     * @param  iterable<ConceptMapping>  $mappings
     */
    public function normalize(RawFact $rawFact, iterable $mappings, string $normalizationVersion): NormalizationOutcome
    {
        if (trim($normalizationVersion) === '') {
            throw new InvalidArgumentException('A normalization version is required.');
        }

        $candidates = array_values(array_filter(
            iterator_to_array($mappings, preserve_keys: false),
            fn (mixed $mapping): bool => $mapping instanceof ConceptMapping
                && (string) $mapping->source_concept === (string) $rawFact->source_concept,
        ));

        if ($candidates === []) {
            return $this->reviewOutcome($rawFact, 'No approved mapping exists for this source concept.', 'UNMAPPED');
        }

        $approved = array_values(array_filter(
            $candidates,
            fn (ConceptMapping $mapping): bool => strtoupper((string) $mapping->status) === 'APPROVED',
        ));

        if ($approved === []) {
            return $this->reviewOutcome($rawFact, 'A mapping exists, but no approved mapping is available.', 'REVIEW_REQUIRED');
        }

        $applicable = array_values(array_filter(
            $approved,
            fn (ConceptMapping $mapping): bool => $this->matchesContext($rawFact, $mapping),
        ));

        if ($applicable === []) {
            return $this->reviewOutcome($rawFact, $this->contextMismatchReason($rawFact, $approved[0]), 'REVIEW_REQUIRED');
        }

        if (count($applicable) > 1) {
            return $this->reviewOutcome($rawFact, 'Multiple approved mappings apply to this source fact.', 'REVIEW_REQUIRED');
        }

        $mapping = $applicable[0];
        $canonical = $mapping->relationLoaded('canonicalConcept')
            ? $mapping->getRelation('canonicalConcept')
            : null;

        if (! $canonical instanceof CanonicalConcept) {
            return $this->reviewOutcome($rawFact, 'The approved mapping references a missing canonical concept.', 'REVIEW_REQUIRED');
        }

        if ($this->hasInvalidNumericValue($rawFact)) {
            return $this->reviewOutcome($rawFact, 'The raw fact contains an invalid numeric value.', 'REVIEW_REQUIRED');
        }

        $value = $this->numericValue($rawFact);

        if ($value !== null) {
            $value = $this->applySignConvention(
                $value,
                (string) ($mapping->sign_convention ?: $canonical->sign_convention ?: 'AS_REPORTED'),
            );

            if ($value === null) {
                return $this->reviewOutcome($rawFact, 'The sign convention is unsupported or the numeric value is invalid.', 'REVIEW_REQUIRED');
            }
        }

        $exceptionVersions = [];

        foreach ($this->exceptionRules as $exceptionRule) {
            if (! $exceptionRule instanceof NormalizationExceptionRule || ! $exceptionRule->appliesTo($rawFact, $mapping)) {
                continue;
            }

            $value = $exceptionRule->transform($value ?? '');

            if ($value !== '' && ! $this->isNumeric($value)) {
                return $this->reviewOutcome($rawFact, 'A normalization exception produced an invalid numeric value.', 'REVIEW_REQUIRED');
            }

            $exceptionVersions[] = $exceptionRule->code().'@'.$exceptionRule->version();
        }

        $context = $rawFact->relationLoaded('context') ? $rawFact->getRelation('context') : null;
        $unit = $rawFact->relationLoaded('unit') ? $rawFact->getRelation('unit') : null;
        $filing = $rawFact->relationLoaded('filing') ? $rawFact->getRelation('filing') : null;

        return new NormalizationOutcome(
            status: 'NORMALIZED',
            canonicalConcept: (string) $canonical->code,
            value: $value,
            scope: $context?->scope,
            period: $filing?->fiscal_period,
            periodStart: $this->dateValue($context?->period_type === 'DURATION' ? $context?->start_date : null),
            periodEnd: $this->dateValue($context?->period_type === 'DURATION' ? $context?->end_date : $context?->instant_date),
            currency: $unit?->currency,
            sourceConcept: (string) $rawFact->source_concept,
            mappingRuleId: (string) $mapping->mapping_rule_id,
            mappingRuleVersion: (int) $mapping->rule_version,
            exceptionRuleVersion: $exceptionVersions === [] ? 'NONE' : implode(',', $exceptionVersions),
        );
    }

    private function matchesContext(RawFact $rawFact, ConceptMapping $mapping): bool
    {
        $context = $rawFact->relationLoaded('context') ? $rawFact->getRelation('context') : null;
        $canonical = $mapping->relationLoaded('canonicalConcept') ? $mapping->getRelation('canonicalConcept') : null;
        $scope = $context?->scope;
        $periodType = strtoupper((string) ($context?->period_type ?? ''));

        foreach ([$mapping->allowed_scope, $canonical instanceof CanonicalConcept ? $canonical->allowed_scope : null] as $allowedScopes) {
            $allowedScopes = array_values(array_filter(array_map('strtoupper', (array) $allowedScopes)));

            if ($allowedScopes !== [] && ! in_array(strtoupper((string) $scope), $allowedScopes, true)) {
                return false;
            }
        }

        foreach ([$mapping->period_type, $canonical instanceof CanonicalConcept ? $canonical->period_type : null] as $expectedPeriodType) {
            $expectedPeriodType = strtoupper(trim((string) $expectedPeriodType));

            if ($expectedPeriodType !== '' && $expectedPeriodType !== 'ANY' && $expectedPeriodType !== $periodType) {
                return false;
            }
        }

        return true;
    }

    private function contextMismatchReason(RawFact $rawFact, ConceptMapping $mapping): string
    {
        $context = $rawFact->relationLoaded('context') ? $rawFact->getRelation('context') : null;
        $scope = strtoupper((string) ($context?->scope ?? ''));
        $periodType = strtoupper((string) ($context?->period_type ?? ''));
        $allowedScopes = array_values(array_filter(array_map('strtoupper', (array) $mapping->allowed_scope)));

        if ($allowedScopes !== [] && ! in_array($scope, $allowedScopes, true)) {
            return 'The raw fact scope does not match the approved mapping scope.';
        }

        if (trim((string) $mapping->period_type) !== '' && strtoupper((string) $mapping->period_type) !== $periodType) {
            return 'The raw fact period type does not match the approved mapping period type.';
        }

        return 'The raw fact context does not match the approved mapping constraints.';
    }

    private function numericValue(RawFact $rawFact): ?string
    {
        if ((bool) $rawFact->is_nil) {
            return null;
        }

        $value = $rawFact->normalized_numeric_value ?? $rawFact->raw_value;

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (! $this->isNumeric((string) $value)) {
            return null;
        }

        return ltrim(trim((string) $value), '+');
    }

    private function applySignConvention(string $value, string $signConvention): ?string
    {
        $signConvention = strtoupper(trim($signConvention));

        if (! $this->isNumeric($value)) {
            return null;
        }

        return match ($signConvention) {
            '', 'AS_REPORTED', 'KEEP' => ltrim($value, '+'),
            'NEGATE' => $this->isZero($value) ? '0' : ($value[0] === '-' ? substr($value, 1) : '-'.$value),
            'ABSOLUTE', 'POSITIVE' => ltrim($value, '-+'),
            'NEGATIVE' => $this->isZero($value) ? '0' : '-'.ltrim($value, '-+'),
            default => null,
        };
    }

    private function reviewOutcome(RawFact $rawFact, string $reason, string $status): NormalizationOutcome
    {
        return new NormalizationOutcome(
            status: $status,
            canonicalConcept: null,
            value: null,
            scope: null,
            period: null,
            periodStart: null,
            periodEnd: null,
            currency: null,
            sourceConcept: (string) $rawFact->source_concept,
            mappingRuleId: null,
            mappingRuleVersion: null,
            reason: $reason,
        );
    }

    private function isNumeric(string $value): bool
    {
        return preg_match('/\A[+-]?(?:\d+(?:\.\d+)?|\.\d+)\z/', trim($value)) === 1;
    }

    private function isZero(string $value): bool
    {
        return preg_match('/\A[+-]?0(?:\.0+)?\z/', trim($value)) === 1;
    }

    private function hasInvalidNumericValue(RawFact $rawFact): bool
    {
        if ((bool) $rawFact->is_nil) {
            return false;
        }

        $value = $rawFact->normalized_numeric_value ?? $rawFact->raw_value;

        return $value !== null && trim((string) $value) !== '' && ! $this->isNumeric((string) $value);
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}
