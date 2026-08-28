<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

it('creates the financial data engine core tables', function () {
    expect(Schema::hasTable('filings'))->toBeTrue()
        ->and(Schema::hasTable('xbrl_contexts'))->toBeTrue()
        ->and(Schema::hasTable('xbrl_units'))->toBeTrue()
        ->and(Schema::hasTable('xbrl_dimensions'))->toBeTrue()
        ->and(Schema::hasTable('raw_facts'))->toBeTrue()
        ->and(Schema::hasTable('canonical_concepts'))->toBeTrue()
        ->and(Schema::hasTable('concept_mappings'))->toBeTrue()
        ->and(Schema::hasTable('normalized_facts'))->toBeTrue()
        ->and(Schema::hasTable('validation_rules'))->toBeTrue()
        ->and(Schema::hasTable('validation_results'))->toBeTrue()
        ->and(Schema::hasTable('financial_metrics'))->toBeTrue()
        ->and(Schema::hasTable('evidence_records'))->toBeTrue()
        ->and(Schema::hasTable('debt_records'))->toBeTrue()
        ->and(Schema::hasTable('audit_logs'))->toBeTrue();
});
