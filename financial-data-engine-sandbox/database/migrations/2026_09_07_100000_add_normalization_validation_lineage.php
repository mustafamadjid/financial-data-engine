<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('normalized_facts', function (Blueprint $table): void {
            $table->string('normalization_version', 128)->nullable()->after('mapping_rule_version');
            $table->index(['filing_id', 'normalization_version'], 'normalized_facts_filing_normalization_version_idx');
        });

        Schema::table('validation_results', function (Blueprint $table): void {
            $table->string('normalized_dataset_version', 128)->nullable()->after('filing_id');
            $table->string('validation_rule_set_version', 128)->nullable()->after('normalized_dataset_version');
            $table->index(
                ['filing_id', 'normalized_dataset_version', 'validation_rule_set_version'],
                'validation_results_execution_version_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('validation_results', function (Blueprint $table): void {
            $table->dropIndex('validation_results_execution_version_idx');
            $table->dropColumn(['normalized_dataset_version', 'validation_rule_set_version']);
        });

        Schema::table('normalized_facts', function (Blueprint $table): void {
            $table->dropIndex('normalized_facts_filing_normalization_version_idx');
            $table->dropColumn('normalization_version');
        });
    }
};
