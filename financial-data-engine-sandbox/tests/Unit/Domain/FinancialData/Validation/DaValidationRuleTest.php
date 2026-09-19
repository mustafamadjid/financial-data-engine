<?php

use App\Domain\FinancialData\Validation\DaValidationRule;
use App\Domain\FinancialData\Validation\FilingValidationContext;
use App\Models\CanonicalConcept;
use App\Models\Filing;
use App\Models\NormalizedFact;
use Tests\TestCase;

uses(TestCase::class);

function daFact(string $concept, string $value, string $periodType = 'INSTANT'): NormalizedFact
{
    $fact = new NormalizedFact([
        'normalized_fact_id' => 'NF-'.strtoupper($concept),
        'canonical_concept' => $concept,
        'value' => $value,
        'period_start' => $periodType === 'DURATION' ? '2026-01-01' : null,
        'period_end' => '2026-06-30',
    ]);
    $fact->setRelation('canonicalConcept', new CanonicalConcept(['code' => $concept, 'period_type' => $periodType]));

    return $fact;
}

function daValidationContext(array $facts, string $entryPoint = 'general'): FilingValidationContext
{
    return new FilingValidationContext(new Filing([
        'filing_id' => 'FIL-DA-RULE',
        'taxonomy_entry_point' => $entryPoint,
    ]), $facts);
}

it('evaluates ACC-001 using exact decimal arithmetic', function () {
    $rule = new DaValidationRule('ACC-001');
    $result = $rule->evaluate(daValidationContext([
        daFact('total_assets', '61272871000000'),
        daFact('total_liabilities', '23214472000000'),
        daFact('total_equity', '38058399000000'),
    ]));

    expect($result->result)->toBe('PASS')
        ->and($result->severity)->toBe('INFO')
        ->and($result->expectedValue)->toBe('61272871000000.000000000000000000');
});

it('returns a warning for negative total equity', function () {
    $result = (new DaValidationRule('SCL-004'))->evaluate(daValidationContext([
        daFact('total_equity', '-2500000000000'),
    ]));

    expect($result->result)->toBe('REVIEW_REQUIRED')
        ->and($result->severity)->toBe('WARN');
});

it('includes temporary syirkah funds for financesharia', function () {
    $result = (new DaValidationRule('ACC-001'))->evaluate(daValidationContext([
        daFact('total_assets', '1660579336000000'),
        daFact('total_liabilities', '1379511881000000'),
        daFact('total_equity', '270667746000000'),
        daFact('temporary_syirkah_funds', '10399709000000'),
    ], 'http://www.idx.co.id/xbrl/taxonomy/2020-01-01/ep/E24/financesharia'));

    expect($result->result)->toBe('PASS');
});
