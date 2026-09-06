<?php

use App\Models\AuditLog;
use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use App\Models\DebtRecord;
use App\Models\EvidenceRecord;
use App\Models\Filing;
use App\Models\FinancialMetric;
use App\Models\NormalizedFact;
use App\Models\PipelineRun;
use App\Models\RawFact;
use App\Models\ValidationResult;
use App\Models\ValidationRule;
use App\Models\XbrlContext;
use App\Models\XbrlDimension;
use App\Models\XbrlUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;

it('maps every financial schema table to its Eloquent model', function (string $modelClass, string $table, string $primaryKey) {
    $model = new $modelClass;

    expect($model->getTable())->toBe($table)
        ->and($model->getKeyName())->toBe($primaryKey)
        ->and($model->getIncrementing())->toBe($primaryKey === 'id');
})->with([
    [Filing::class, 'filings', 'filing_id'],
    [XbrlContext::class, 'xbrl_contexts', 'context_id'],
    [XbrlUnit::class, 'xbrl_units', 'unit_id'],
    [XbrlDimension::class, 'xbrl_dimensions', 'dimension_id'],
    [RawFact::class, 'raw_facts', 'raw_fact_id'],
    [CanonicalConcept::class, 'canonical_concepts', 'code'],
    [ConceptMapping::class, 'concept_mappings', 'mapping_rule_id'],
    [NormalizedFact::class, 'normalized_facts', 'normalized_fact_id'],
    [ValidationRule::class, 'validation_rules', 'id'],
    [ValidationResult::class, 'validation_results', 'validation_result_id'],
    [FinancialMetric::class, 'financial_metrics', 'metric_id'],
    [EvidenceRecord::class, 'evidence_records', 'evidence_id'],
    [DebtRecord::class, 'debt_records', 'debt_record_id'],
    [AuditLog::class, 'audit_logs', 'id'],
]);

it('casts structured and temporal financial attributes', function () {
    expect((new Filing)->getCasts())->toMatchArray([
        'fiscal_year' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'discovered_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ])
        ->and((new RawFact)->getCasts())->toMatchArray([
            'normalized_numeric_value' => 'decimal:18',
            'is_nil' => 'boolean',
        ])
        ->and((new CanonicalConcept)->getCasts())->toMatchArray([
            'allowed_scope' => 'array',
            'formula_dependency' => 'array',
        ])
        ->and((new ValidationResult)->getCasts())->toMatchArray([
            'normalized_fact_ids' => 'array',
            'checked_at' => 'datetime',
        ]);
});

it('declares mass assignable attributes with the Fillable attribute', function (string $modelClass) {
    expect((new ReflectionClass($modelClass))->getAttributes(Fillable::class))->toHaveCount(1);
})->with([
    Filing::class,
    XbrlContext::class,
    XbrlUnit::class,
    XbrlDimension::class,
    RawFact::class,
    CanonicalConcept::class,
    ConceptMapping::class,
    NormalizedFact::class,
    ValidationRule::class,
    ValidationResult::class,
    FinancialMetric::class,
    EvidenceRecord::class,
    DebtRecord::class,
    AuditLog::class,
]);

it('maps pipeline runs to its migration schema', function () {
    $model = new PipelineRun;

    expect($model->getTable())->toBe('pipeline_runs')
        ->and($model->getKeyName())->toBe('id')
        ->and($model->getFillable())->toBe([
            'filing_id', 'trigger', 'status', 'correlation_id', 'started_at', 'finished_at',
        ])
        ->and($model->getCasts())->toMatchArray([
            'filing_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ]);
});
