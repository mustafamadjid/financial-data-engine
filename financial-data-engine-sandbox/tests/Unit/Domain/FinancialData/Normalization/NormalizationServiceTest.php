<?php

use App\Domain\FinancialData\Normalization\Contracts\NormalizationExceptionRule;
use App\Domain\FinancialData\Normalization\NormalizationService;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\Filing;
use App\Models\RawFact;
use App\Models\XbrlContext;
use App\Models\XbrlUnit;
use Tests\TestCase;

uses(TestCase::class);

function normalizationRawFact(
    string $sourceConcept = 'Assets',
    string $scope = 'CONSOLIDATED',
    string $periodType = 'INSTANT',
    string $value = '100',
): RawFact {
    $filing = new Filing([
        'filing_id' => 'FIL-NORMALIZE-UNIT',
        'fiscal_period' => 'Q2',
        'period_end' => '2026-06-30',
    ]);
    $context = new XbrlContext([
        'context_id' => 'CTX-NORMALIZE-UNIT',
        'filing_id' => $filing->filing_id,
        'scope' => $scope,
        'period_type' => $periodType,
        'instant_date' => $periodType === 'INSTANT' ? '2026-06-30' : null,
        'start_date' => $periodType === 'DURATION' ? '2026-01-01' : null,
        'end_date' => $periodType === 'DURATION' ? '2026-06-30' : null,
    ]);
    $unit = new XbrlUnit(['currency' => 'IDR']);
    $rawFact = new RawFact([
        'raw_fact_id' => 'RAW-NORMALIZE-UNIT',
        'filing_id' => $filing->filing_id,
        'source_concept' => $sourceConcept,
        'normalized_numeric_value' => $value,
        'raw_value' => $value,
        'is_nil' => false,
    ]);
    $rawFact->setRelation('filing', $filing);
    $rawFact->setRelation('context', $context);
    $rawFact->setRelation('unit', $unit);

    return $rawFact;
}

function normalizationMapping(
    string $sourceConcept = 'Assets',
    string $canonicalCode = 'total_assets',
    array $allowedScope = ['CONSOLIDATED'],
    string $periodType = 'INSTANT',
    string $signConvention = 'AS_REPORTED',
    string $status = 'APPROVED',
): ConceptMapping {
    $mapping = new ConceptMapping([
        'mapping_rule_id' => 'MAP-NORMALIZE-UNIT',
        'source_concept' => $sourceConcept,
        'canonical_concept' => $canonicalCode,
        'allowed_scope' => $allowedScope,
        'period_type' => $periodType,
        'sign_convention' => $signConvention,
        'status' => $status,
        'rule_version' => 1,
    ]);
    $mapping->setRelation('canonicalConcept', new CanonicalConcept([
        'code' => $canonicalCode,
        'period_type' => $periodType,
        'sign_convention' => $signConvention,
        'allowed_scope' => $allowedScope,
    ]));

    return $mapping;
}

it('normalizes an exact approved mapping and applies the sign convention', function () {
    $outcome = (new NormalizationService)->normalize(
        normalizationRawFact(value: '100'),
        [normalizationMapping(signConvention: 'NEGATE')],
        '1.0.0',
    );

    expect($outcome->status)->toBe('NORMALIZED')
        ->and($outcome->canonicalConcept)->toBe('total_assets')
        ->and($outcome->value)->toBe('-100.000000000000000000')
        ->and($outcome->mappingRuleId)->toBe('MAP-NORMALIZE-UNIT');
});

it('returns an explicit unmapped outcome without fuzzy matching', function () {
    $outcome = (new NormalizationService)->normalize(
        normalizationRawFact(sourceConcept: 'Assets'),
        [normalizationMapping(sourceConcept: 'Asset')],
        '1.0.0',
    );

    expect($outcome->status)->toBe('UNMAPPED')
        ->and($outcome->canonicalConcept)->toBeNull()
        ->and($outcome->reason)->toContain('approved mapping');
});

it('returns review for a non-approved mapping', function () {
    $outcome = (new NormalizationService)->normalize(
        normalizationRawFact(),
        [normalizationMapping(status: 'REVIEW_REQUIRED')],
        '1.0.0',
    );

    expect($outcome->status)->toBe('REVIEW_REQUIRED')
        ->and($outcome->canonicalConcept)->toBeNull();
});

it('returns review for an invalid numeric source value', function () {
    $rawFact = normalizationRawFact();
    $rawFact->forceFill(['normalized_numeric_value' => null, 'raw_value' => 'not-a-number']);
    $outcome = (new NormalizationService)->normalize(
        $rawFact,
        [normalizationMapping()],
        '1.0.0',
    );

    expect($outcome->status)->toBe('REVIEW_REQUIRED')
        ->and($outcome->reason)->toContain('invalid numeric');
});

it('returns review when scope or period type does not match', function (string $reason) {
    $rawFact = $reason === 'scope'
        ? normalizationRawFact(scope: 'PARENT')
        : normalizationRawFact(periodType: 'DURATION');
    $mapping = normalizationMapping();
    $outcome = (new NormalizationService)->normalize($rawFact, [$mapping], '1.0.0');

    expect($outcome->status)->toBe('REVIEW_REQUIRED')
        ->and($outcome->reason)->toContain($reason);
})->with([
    ['scope'],
    ['period type'],
]);

it('applies a versioned exception rule after the mapping sign convention', function () {
    $exceptionRule = new class implements NormalizationExceptionRule
    {
        public function code(): string
        {
            return 'EX-NEGATE';
        }

        public function version(): string
        {
            return '1';
        }

        public function appliesTo(RawFact $rawFact, ConceptMapping $mapping): bool
        {
            return $rawFact->source_concept === 'Assets' && $mapping->mapping_rule_id === 'MAP-NORMALIZE-UNIT';
        }

        public function transform(string $value): string
        {
            return '-'.$value;
        }
    };

    $outcome = (new NormalizationService([$exceptionRule]))->normalize(
        normalizationRawFact(value: '100'),
        [normalizationMapping()],
        '1.0.0',
    );

    expect($outcome->status)->toBe('NORMALIZED')
        ->and($outcome->value)->toBe('-100.000000000000000000')
        ->and($outcome->exceptionRuleVersion)->toBe('EX-NEGATE@1');
});
