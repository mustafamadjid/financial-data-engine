<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Validation\Contracts\ValidationRule;
use App\Models\Filing;
use App\Models\NormalizedFact;
use App\Models\RestatementEvent;

final class DaValidationRule implements ValidationRule
{
    public function __construct(private readonly string $ruleCode) {}

    public function code(): string
    {
        return $this->ruleCode;
    }

    public function version(): string
    {
        return '1';
    }

    public function appliesTo(FilingValidationContext $context): bool
    {
        return true;
    }

    public function evaluate(FilingValidationContext $context): RuleResult
    {
        return match ($this->ruleCode) {
            'ACC-001' => $this->accounting($context),
            'HRY-001' => $this->equation($context, 'total_equity', ['equity_parent', 'non_controlling_interests'], 'Total equity attribution mismatch.'),
            'HRY-002' => $this->classification($context, 'total_assets', ['current_assets', 'non_current_assets'], 'Asset classification mismatch.'),
            'HRY-003' => $this->classification($context, 'total_liabilities', ['current_liabilities', 'non_current_liabilities'], 'Liability classification mismatch.'),
            'HRY-004' => $this->grossProfit($context),
            'HRY-005' => $this->equation($context, 'profit_loss', ['profit_loss_parent', 'profit_loss_nci'], 'Profit attribution mismatch.'),
            'HRY-006' => $this->equation($context, 'net_change_cash', ['cash_flow_operating', 'cash_flow_investing', 'cash_flow_financing'], 'Cash flow subtotal mismatch.'),
            'CTX-001' => $this->periodicity($context),
            'CTX-002' => $this->periodAlignment($context),
            'CTX-003' => $this->undimensioned($context),
            'CTX-004' => $this->duplicateConflict($context),
            'SCL-001' => $this->currency($context),
            'SCL-002' => $this->epsUnit($context),
            'SCL-003' => $this->positiveAssets($context),
            'SCL-004' => $this->negativeEquity($context),
            'SCL-005' => $this->cashTieOut($context),
            'CRX-001' => $this->restatementConsistency($context),
            'CRX-002' => $this->comparativeConsistency($context),
            'SCP-001' => $this->scope($context),
            'SCP-002' => $this->nilPreservation($context),
            default => new RuleResult('SKIPPED', 'INFO', 'Rule is not part of the DA-1-3 registry.'),
        };
    }

    private function accounting(FilingValidationContext $context): RuleResult
    {
        $required = $this->values($context, ['total_assets', 'total_liabilities', 'total_equity']);
        if (count($required) < 3) {
            return $this->skipped('ACC-001 requires total assets, liabilities, and equity.');
        }

        $dst = $this->value($context, 'temporary_syirkah_funds') ?? '0';
        $expected = $this->add($required['total_liabilities'], $required['total_equity']);
        if ($this->isFinance($context->filing)) {
            $expected = $this->add($expected, $dst);
        }

        return $this->comparison($context, ['total_assets', 'total_liabilities', 'total_equity', 'temporary_syirkah_funds'], $required['total_assets'], $expected, $this->isFinance($context->filing) ? 'Bank balance includes TemporarySyirkahFunds.' : 'Balance sheet equation holds.');
    }

    /** @param list<string> $components */
    private function equation(FilingValidationContext $context, string $total, array $components, string $message): RuleResult
    {
        $totalValue = $this->value($context, $total);
        $values = $this->values($context, $components);
        if ($totalValue === null || ($components !== [] && count($values) < 1)) {
            return $this->skipped("{$total} attribution inputs are not fully reported.");
        }

        $sum = '0';
        $evidence = [$total];
        foreach ($components as $component) {
            $sum = $this->add($sum, $values[$component] ?? '0');
            if (isset($values[$component])) {
                $evidence[] = $component;
            }
        }

        return $this->comparison($context, $evidence, $totalValue, $sum, $message);
    }

    /** @param list<string> $components */
    private function classification(FilingValidationContext $context, string $total, array $components, string $message): RuleResult
    {
        $values = $this->values($context, [$total, ...$components]);
        if ($this->isFinance($context->filing) && ! isset($values[$components[0]]) && ! isset($values[$components[1]])) {
            return new RuleResult('SKIPPED', 'INFO', 'Unclassified banking balance sheet accepted.');
        }
        if (count($values) < 3) {
            return $this->skipped("{$total} classification is not fully reported.");
        }

        return $this->comparison($context, array_keys($values), $values[$total], $this->add($values[$components[0]], $values[$components[1]]), $message);
    }

    private function grossProfit(FilingValidationContext $context): RuleResult
    {
        if ($this->isFinance($context->filing) || str_contains(strtolower((string) $context->filing->taxonomy_entry_point), 'infrastructure')) {
            return new RuleResult('SKIPPED', 'INFO', 'Gross profit rule is not applicable to this entry point.');
        }
        $values = $this->values($context, ['gross_profit', 'revenue', 'cost_of_revenue']);
        if (count($values) < 3) {
            return $this->skipped('Gross profit inputs are not fully reported.');
        }

        return $this->comparison($context, array_keys($values), $values['gross_profit'], $this->sub($values['revenue'], $values['cost_of_revenue']), 'Gross profit equation holds.');
    }

    private function periodicity(FilingValidationContext $context): RuleResult
    {
        $facts = collect($context->normalizedFacts);
        foreach ($facts as $fact) {
            if (! $fact instanceof NormalizedFact) {
                continue;
            }
            $period = strtoupper((string) $fact->canonicalConcept?->period_type);
            if ($period === 'DURATION' && ($fact->period_start === null || $fact->period_end === null || $fact->period_start->format('m-d') !== '01-01' || $fact->period_start->gt($fact->period_end))) {
                return new RuleResult('FAIL', 'ERROR', 'Duration context must begin on January 1.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
            }
            if ($period === 'INSTANT' && $fact->period_end === null) {
                return new RuleResult('FAIL', 'ERROR', 'Instant context must include an instant date.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
            }
        }

        return new RuleResult('PASS', 'INFO', 'Context dates are valid.');
    }

    private function periodAlignment(FilingValidationContext $context): RuleResult
    {
        foreach ($context->normalizedFacts as $fact) {
            if (! $fact instanceof NormalizedFact || ! $fact->canonicalConcept) {
                continue;
            }
            if (strtoupper((string) $fact->canonicalConcept->period_type) !== strtoupper((string) $this->periodType($fact))) {
                return new RuleResult('FAIL', 'ERROR', 'Concept period type does not match context period type.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
            }
        }

        return new RuleResult('PASS', 'INFO', 'Concept period types match contexts.');
    }

    private function undimensioned(FilingValidationContext $context): RuleResult
    {
        foreach ($context->normalizedFacts as $fact) {
            if (! $fact instanceof NormalizedFact || ! $fact->rawFact?->context) {
                continue;
            }
            $contextModel = $fact->rawFact->context;
            if ($contextModel->relationLoaded('dimensions') && $contextModel->dimensions->isNotEmpty()) {
                return new RuleResult('FAIL', 'ERROR', 'Dimensioned fact cannot be a default statement total.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
            }
        }

        return new RuleResult('PASS', 'INFO', 'Default facts are undimensioned.');
    }

    private function duplicateConflict(FilingValidationContext $context): RuleResult
    {
        $groups = collect($context->normalizedFacts)->filter(fn ($fact): bool => $fact instanceof NormalizedFact)->groupBy(fn (NormalizedFact $fact): string => implode('|', [(string) $fact->rawFact?->source_namespace, (string) $fact->source_concept, (string) $fact->rawFact?->context_ref, (string) $fact->rawFact?->unit_ref]));
        foreach ($groups as $facts) {
            $values = $facts->pluck('value')->unique();
            if ($values->count() > 1) {
                return new RuleResult('FAIL', 'ERROR', 'Conflicting duplicate facts detected.', normalizedFactIds: $facts->pluck('normalized_fact_id')->values()->all());
            }
        }

        return new RuleResult('PASS', 'INFO', 'No conflicting duplicate facts.');
    }

    private function currency(FilingValidationContext $context): RuleResult
    {
        $currencies = collect($context->normalizedFacts)
            ->pluck('currency')
            ->filter()
            ->map(fn ($value): string => strtoupper((string) $value))
            ->unique();
        $declared = strtoupper((string) ($context->filing->presentation_currency ?? ''));
        if ($currencies->count() > 1 || ($declared !== '' && $currencies->contains(fn (string $value): bool => $value !== $declared))) {
            return new RuleResult('FAIL', 'ERROR', 'Statement currencies are not uniform with DEI presentation currency.');
        }

        return new RuleResult('PASS', 'INFO', 'Statement currency is uniform.');
    }

    private function epsUnit(FilingValidationContext $context): RuleResult
    {
        foreach ($context->normalizedFacts as $fact) {
            if ($fact instanceof NormalizedFact && str_starts_with((string) $fact->canonical_concept, 'eps_') && ! str_contains(strtolower((string) $fact->currency), 'pershares')) {
                $unit = $fact->rawFact?->unit?->source_unit_id ?? $fact->currency;
                if (! str_contains(strtolower((string) $unit), 'pershares')) {
                    return new RuleResult('FAIL', 'ERROR', 'EPS unit must be per-share.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
                }
            }
        }

        return new RuleResult('PASS', 'INFO', 'EPS units are per-share.');
    }

    private function positiveAssets(FilingValidationContext $context): RuleResult
    {
        $value = $this->value($context, 'total_assets');
        if ($value === null) {
            return $this->skipped('Total assets is not reported.');
        }

        return $this->compare($value, '0') > 0
            ? new RuleResult('PASS', 'INFO', 'Total assets is strictly positive.')
            : new RuleResult('FAIL', 'ERROR', 'Total assets cannot be non-positive.');
    }

    private function negativeEquity(FilingValidationContext $context): RuleResult
    {
        $value = $this->value($context, 'total_equity');
        if ($value === null) {
            return $this->skipped('Total equity is not reported.');
        }

        return $this->compare($value, '0') < 0
            ? new RuleResult('REVIEW_REQUIRED', 'WARN', 'Negative equity indicates a capital deficit.')
            : new RuleResult('PASS', 'INFO', 'Total equity is non-negative.');
    }

    private function cashTieOut(FilingValidationContext $context): RuleResult
    {
        $bs = $this->value($context, 'cash_and_cash_equivalents');
        $cf = $this->value($context, 'ending_cash_cash_flow');
        if ($bs === null || $cf === null) {
            return $this->skipped('BS cash or CF ending cash is not reported.');
        }

        return $this->compare($bs, $cf) === 0
            ? new RuleResult('PASS', 'INFO', 'BS cash matches CF ending cash.')
            : new RuleResult('REVIEW_REQUIRED', 'WARN', 'BS cash differs from CF ending cash.', expectedValue: $bs, actualValue: $cf);
    }

    private function restatementConsistency(FilingValidationContext $context): RuleResult
    {
        $filing = $context->filing;
        if (trim((string) $filing->supersedes_filing_id) === '') {
            return new RuleResult('PASS', 'INFO', 'No superseded filing is attached; restatement comparison is not required.');
        }

        $prior = Filing::query()->find($filing->supersedes_filing_id);
        if (! $prior) {
            return new RuleResult('REVIEW_REQUIRED', 'WARN', 'Superseded filing is unavailable for restatement comparison.');
        }

        $current = collect($context->normalizedFacts)
            ->filter(fn ($fact): bool => $fact instanceof NormalizedFact && $fact->value !== null)
            ->keyBy(fn (NormalizedFact $fact): string => (string) $fact->canonical_concept);
        $previous = NormalizedFact::query()
            ->where('filing_id', $prior->filing_id)
            ->whereNotNull('value')
            ->get(['normalized_fact_id', 'canonical_concept', 'value'])
            ->keyBy(fn (NormalizedFact $fact): string => (string) $fact->canonical_concept);

        $changed = [];
        foreach ($current as $concept => $fact) {
            $old = $previous->get($concept);
            if ($old && $this->compare((string) $fact->value, (string) $old->value) !== 0) {
                $changed[] = $concept;
                RestatementEvent::query()->firstOrCreate(
                    [
                        'filing_id' => (string) $filing->filing_id,
                        'superseded_filing_id' => (string) $prior->filing_id,
                        'canonical_concept' => $concept,
                    ],
                    [
                        'current_normalized_fact_id' => (string) $fact->normalized_fact_id,
                        'prior_normalized_fact_id' => (string) $old->normalized_fact_id,
                        'current_value' => (string) $fact->value,
                        'prior_value' => (string) $old->value,
                        'rule_code' => 'CRX-001',
                        'rule_version' => 1,
                        'detected_at' => now(),
                    ],
                );
            }
        }

        return $changed === []
            ? new RuleResult('PASS', 'INFO', 'Restated filing values are consistent with the superseded filing.')
            : new RuleResult('REVIEW_REQUIRED', 'WARN', 'Restated filing changes require review.', normalizedFactIds: $this->factIdsFor($context, $changed));
    }

    private function comparativeConsistency(FilingValidationContext $context): RuleResult
    {
        $filing = $context->filing;
        $period = strtoupper((string) $filing->fiscal_period);
        $priorPeriod = match ($period) {
            'Q2' => 'Q1',
            'Q3' => 'Q2',
            'FY', 'ANNUAL' => 'Q3',
            default => null,
        };
        if ($priorPeriod === null || $filing->issuer_code === null || $filing->fiscal_year === null) {
            return new RuleResult('PASS', 'INFO', 'No adjacent comparative period is required.');
        }

        $prior = Filing::query()
            ->where('issuer_code', $filing->issuer_code)
            ->where('fiscal_year', $filing->fiscal_year)
            ->where('fiscal_period', $priorPeriod)
            ->orderByDesc('revision_number')
            ->first();
        if (! $prior) {
            return new RuleResult('REVIEW_REQUIRED', 'WARN', 'Adjacent comparative filing is unavailable.');
        }

        $priorFacts = NormalizedFact::query()
            ->where('filing_id', $prior->filing_id)
            ->whereIn('canonical_concept', ['total_assets', 'total_liabilities', 'total_equity', 'revenue', 'profit_loss'])
            ->whereNotNull('value')
            ->get(['canonical_concept', 'value'])
            ->keyBy(fn (NormalizedFact $fact): string => (string) $fact->canonical_concept);
        $currentFacts = collect($context->normalizedFacts)->filter(fn ($fact): bool => $fact instanceof NormalizedFact && $fact->value !== null);
        $evidence = [];
        foreach ($currentFacts as $fact) {
            if ($priorFacts->has((string) $fact->canonical_concept)) {
                $evidence[] = (string) $fact->normalized_fact_id;
            }
        }

        return $evidence === []
            ? new RuleResult('SKIPPED', 'INFO', 'No overlapping comparative concepts are available.', normalizedFactIds: [])
            : new RuleResult('PASS', 'INFO', 'Adjacent comparative period is available.', normalizedFactIds: array_values(array_unique($evidence)));
    }

    private function scope(FilingValidationContext $context): RuleResult
    {
        $scopes = collect($context->normalizedFacts)->pluck('scope')->filter()->unique();
        if ($scopes->isEmpty() || $scopes->contains('UNKNOWN')) {
            return new RuleResult('FAIL', 'ERROR', 'Entity reporting scope is unknown.');
        }

        foreach ($context->normalizedFacts as $fact) {
            if (! $fact instanceof NormalizedFact || ! $fact->canonicalConcept) {
                continue;
            }
            $allowed = $fact->canonicalConcept->allowed_scope;
            if (is_array($allowed) && $allowed !== [] && ! in_array((string) $fact->scope, $allowed, true)) {
                return new RuleResult('FAIL', 'ERROR', 'Fact scope is not permitted by the canonical concept.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
            }
        }

        return new RuleResult('PASS', 'INFO', 'Entity reporting scope is resolved.');
    }

    private function nilPreservation(FilingValidationContext $context): RuleResult
    {
        if (collect($context->normalizationIssues)->contains(fn (array $issue): bool => str_contains(strtolower((string) ($issue['reason'] ?? '')), 'zero'))) {
            return new RuleResult('FAIL', 'ERROR', 'Nil fact cannot be coerced to zero.');
        }
        foreach ($context->normalizedFacts as $fact) {
            if ($fact instanceof NormalizedFact && $fact->rawFact?->is_nil && $fact->value !== null) {
                return new RuleResult('FAIL', 'ERROR', 'Nil fact cannot be coerced to a numeric value.', normalizedFactIds: [(string) $fact->normalized_fact_id]);
            }
        }

        return new RuleResult('PASS', 'INFO', 'Nil facts are preserved.');
    }

    /** @param list<string> $concepts */
    private function comparison(FilingValidationContext $context, array $concepts, string $actual, string $expected, string $message): RuleResult
    {
        $diff = $this->abs($this->sub($actual, $expected));
        $tolerance = '1000';
        $evidence = $this->factIdsFor($context, $concepts);

        return $this->compare($diff, $tolerance) <= 0
            ? new RuleResult('PASS', 'INFO', $message, expectedValue: $expected, actualValue: $actual, tolerance: $tolerance, normalizedFactIds: $evidence)
            : new RuleResult('FAIL', 'ERROR', $message, expectedValue: $expected, actualValue: $actual, tolerance: $tolerance, normalizedFactIds: $evidence);
    }

    private function skipped(string $message): RuleResult
    {
        return new RuleResult('SKIPPED', 'INFO', $message);
    }

    private function value(FilingValidationContext $context, string $concept): ?string
    {
        foreach ($context->normalizedFacts as $fact) {
            if ($fact instanceof NormalizedFact && (string) $fact->canonical_concept === $concept && $fact->value !== null) {
                return (string) $fact->value;
            }
        }

        return null;
    }

    /** @param list<string> $concepts @return array<string, string> */
    private function values(FilingValidationContext $context, array $concepts): array
    {
        $values = [];
        foreach ($concepts as $concept) {
            $value = $this->value($context, $concept);
            if ($value !== null) {
                $values[$concept] = $value;
            }
        }

        return $values;
    }

    /** @param list<string> $concepts @return list<string> */
    private function factIdsFor(FilingValidationContext $context, array $concepts): array
    {
        return collect($context->normalizedFacts)
            ->filter(fn ($fact): bool => $fact instanceof NormalizedFact && in_array((string) $fact->canonical_concept, $concepts, true))
            ->pluck('normalized_fact_id')
            ->values()
            ->all();
    }

    private function periodType(NormalizedFact $fact): string
    {
        if ($fact->period_start !== null) {
            return 'DURATION';
        }

        return 'INSTANT';
    }

    private function isFinance(Filing $filing): bool
    {
        return str_contains(strtolower((string) $filing->taxonomy_entry_point), 'financesharia');
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, 18);
    }

    private function add(string $left, string $right): string
    {
        return bcadd($left, $right, 18);
    }

    private function sub(string $left, string $right): string
    {
        return bcsub($left, $right, 18);
    }

    private function abs(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }
}
