<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('filings', function (Blueprint $table) {
            $table->string('filing_id', 128)->primary();
            $table->string('issuer_code', 32);
            $table->string('report_type', 50)->nullable();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->string('fiscal_period', 20)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end');
            $table->text('source_url');
            $table->string('source_type', 50);
            $table->text('storage_path')->nullable();
            $table->string('source_hash', 128);
            $table->unsignedInteger('revision_number');
            $table->string('supersedes_filing_id', 128)->nullable();
            $table->dateTimeTz('discovered_at')->nullable();
            $table->dateTimeTz('downloaded_at')->nullable();
            $table->timestamps();

            $table->index('issuer_code');
            $table->index('period_end');
            $table->index(['issuer_code', 'fiscal_year', 'fiscal_period']);
            $table->index('source_hash');
            $table->index('supersedes_filing_id');

            $table->foreign('supersedes_filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
        });

        Schema::create('xbrl_contexts', function (Blueprint $table) {
            $table->string('context_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('source_context_id', 255);
            $table->string('entity_identifier', 255);
            $table->string('scope', 50)->nullable();
            $table->string('period_type', 50);
            $table->date('instant_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('context_status', 50);
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->unique(['filing_id', 'source_context_id']);
            $table->index('filing_id');
        });

        Schema::create('xbrl_units', function (Blueprint $table) {
            $table->string('unit_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('source_unit_id', 255);
            $table->string('unit_type', 50);
            $table->string('measure', 255)->nullable();
            $table->string('currency', 32)->nullable();
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->unique(['filing_id', 'source_unit_id']);
            $table->index('filing_id');
        });

        Schema::create('xbrl_dimensions', function (Blueprint $table) {
            $table->string('dimension_id', 128)->primary();
            $table->string('context_id', 128);
            $table->string('axis', 255);
            $table->string('member', 255)->nullable();
            $table->text('typed_value')->nullable();
            $table->timestamps();

            $table->foreign('context_id')
                ->references('context_id')
                ->on('xbrl_contexts')
                ->restrictOnDelete();
            $table->index('context_id');
            $table->index('axis');
        });

        Schema::create('raw_facts', function (Blueprint $table) {
            $table->string('raw_fact_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('source_concept', 255);
            $table->string('source_namespace', 255)->nullable();
            $table->longText('raw_value');
            $table->decimal('normalized_numeric_value', 65, 18)->nullable();
            $table->string('context_ref', 128);
            $table->string('unit_ref', 128)->nullable();
            $table->string('decimals', 50)->nullable();
            $table->string('precision', 50)->nullable();
            $table->boolean('is_nil');
            $table->string('fact_status', 50);
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->foreign('context_ref')
                ->references('context_id')
                ->on('xbrl_contexts')
                ->restrictOnDelete();
            $table->foreign('unit_ref')
                ->references('unit_id')
                ->on('xbrl_units')
                ->restrictOnDelete();

            $table->index('filing_id');
            $table->index('source_concept');
            $table->index('context_ref');
            $table->index('unit_ref');
            $table->index('fact_status');
        });

        Schema::create('canonical_concepts', function (Blueprint $table) {
            $table->string('code', 100)->primary();
            $table->string('name', 255);
            $table->string('statement', 50);
            $table->string('period_type', 50);
            $table->string('sign_convention', 50)->nullable();
            $table->json('allowed_scope')->nullable();
            $table->string('required_status', 50)->nullable();
            $table->json('formula_dependency')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('concept_mappings', function (Blueprint $table) {
            $table->string('mapping_rule_id', 128)->primary();
            $table->string('source_concept', 255);
            $table->string('entry_point', 255)->nullable();
            $table->string('canonical_concept', 100);
            $table->json('allowed_scope')->nullable();
            $table->string('period_type', 50)->nullable();
            $table->string('sign_convention', 50)->nullable();
            $table->string('status', 50);
            $table->text('rationale')->nullable();
            $table->string('reviewer', 255)->nullable();
            $table->unsignedInteger('rule_version');
            $table->timestamps();

            $table->foreign('canonical_concept')
                ->references('code')
                ->on('canonical_concepts')
                ->restrictOnDelete();
            $table->index('source_concept');
            $table->index('canonical_concept');
            $table->index('status');
            $table->index(['source_concept', 'entry_point', 'rule_version']);
        });

        Schema::create('normalized_facts', function (Blueprint $table) {
            $table->string('normalized_fact_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('raw_fact_id', 128);
            $table->string('issuer_code', 32);
            $table->string('period', 50)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('canonical_concept', 100);
            $table->decimal('value', 65, 18)->nullable();
            $table->string('currency', 32)->nullable();
            $table->string('scope', 50)->nullable();
            $table->string('data_type', 50);
            $table->string('source_concept', 255);
            $table->string('mapping_rule_id', 128);
            $table->unsignedInteger('mapping_rule_version');
            $table->string('normalization_status', 50);
            $table->string('validation_status', 50);
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->foreign('raw_fact_id')
                ->references('raw_fact_id')
                ->on('raw_facts')
                ->restrictOnDelete();
            $table->foreign('canonical_concept')
                ->references('code')
                ->on('canonical_concepts')
                ->restrictOnDelete();
            $table->foreign('mapping_rule_id')
                ->references('mapping_rule_id')
                ->on('concept_mappings')
                ->restrictOnDelete();

            $table->index('filing_id');
            $table->index('raw_fact_id');
            $table->index('canonical_concept');
            $table->index('mapping_rule_id');
            $table->index('normalization_status');
            $table->index('validation_status');
        });

        Schema::create('validation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 100);
            $table->text('description');
            $table->string('severity', 50);
            $table->json('inputs')->nullable();
            $table->decimal('tolerance', 65, 18)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('rule_version');
            $table->timestamps();

            $table->unique(['rule_code', 'rule_version']);
            $table->index('enabled');
            $table->index('severity');
        });

        Schema::create('validation_results', function (Blueprint $table) {
            $table->string('validation_result_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->json('normalized_fact_ids')->nullable();
            $table->string('rule_code', 100);
            $table->unsignedInteger('rule_version');
            $table->string('result', 50);
            $table->string('severity', 50);
            $table->text('message')->nullable();
            $table->decimal('expected_value', 65, 18)->nullable();
            $table->decimal('actual_value', 65, 18)->nullable();
            $table->decimal('tolerance', 65, 18)->nullable();
            $table->dateTimeTz('checked_at');
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->index('filing_id');
            $table->index(['rule_code', 'rule_version']);
            $table->index('result');
            $table->index('severity');
        });

        Schema::create('financial_metrics', function (Blueprint $table) {
            $table->string('metric_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('issuer_code', 32);
            $table->string('period', 50)->nullable();
            $table->string('metric_code', 100);
            $table->decimal('value', 65, 18)->nullable();
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('formula_version');
            $table->json('input_fact_ids');
            $table->string('calculation_status', 50);
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->index('filing_id');
            $table->index('issuer_code');
            $table->index('metric_code');
            $table->index(
                ['issuer_code', 'period', 'metric_code', 'formula_version'],
                'financial_metrics_period_metric_version_idx'
            );
        });

        Schema::create('evidence_records', function (Blueprint $table) {
            $table->string('evidence_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->text('source_document');
            $table->text('source_section')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->string('source_concept', 255)->nullable();
            $table->string('context_ref', 128)->nullable();
            $table->longText('text_reference')->nullable();
            $table->string('extraction_method', 50);
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->index('filing_id');
            $table->index('extraction_method');
        });

        Schema::create('debt_records', function (Blueprint $table) {
            $table->string('debt_record_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('borrower', 255);
            $table->string('creditor', 255)->nullable();
            $table->string('facility', 255)->nullable();
            $table->decimal('outstanding_amount', 65, 18)->nullable();
            $table->decimal('limit_amount', 65, 18)->nullable();
            $table->string('currency', 32)->nullable();
            $table->decimal('interest_or_profit_rate', 38, 18)->nullable();
            $table->date('maturity_date')->nullable();
            $table->text('collateral')->nullable();
            $table->text('purpose')->nullable();
            $table->string('classification', 50);
            $table->string('classification_status', 50);
            $table->json('evidence_ids');
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->index('filing_id');
            $table->index('classification');
            $table->index('classification_status');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_id', 128)->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->string('entity_id', 128);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('rationale')->nullable();
            $table->string('filing_id', 128)->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->nullOnDelete();
            $table->index('actor_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('filing_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('debt_records');
        Schema::dropIfExists('evidence_records');
        Schema::dropIfExists('financial_metrics');
        Schema::dropIfExists('validation_results');
        Schema::dropIfExists('validation_rules');
        Schema::dropIfExists('normalized_facts');
        Schema::dropIfExists('concept_mappings');
        Schema::dropIfExists('canonical_concepts');
        Schema::dropIfExists('raw_facts');
        Schema::dropIfExists('xbrl_dimensions');
        Schema::dropIfExists('xbrl_units');
        Schema::dropIfExists('xbrl_contexts');
        Schema::dropIfExists('filings');
    }
};
