<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['xbrl_contexts', 'xbrl_units', 'xbrl_dimensions', 'raw_facts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('parser_version', 50)->default('1.0.0');
                $table->string('parser_config_version', 50)->default('1.0.0');
            });
        }

        Schema::table('xbrl_contexts', function (Blueprint $table): void {
            $table->dropUnique('xbrl_contexts_filing_id_source_context_id_unique');
            $table->unique(
                ['filing_id', 'parser_version', 'parser_config_version', 'source_context_id'],
                'xbrl_contexts_extraction_identity_unique',
            );
        });

        Schema::table('xbrl_units', function (Blueprint $table): void {
            $table->dropUnique('xbrl_units_filing_id_source_unit_id_unique');
            $table->unique(
                ['filing_id', 'parser_version', 'parser_config_version', 'source_unit_id'],
                'xbrl_units_extraction_identity_unique',
            );
        });

        Schema::table('xbrl_dimensions', function (Blueprint $table): void {
            $table->index(
                ['context_id', 'parser_version', 'parser_config_version'],
                'xbrl_dimensions_extraction_idx',
            );
        });

        Schema::table('raw_facts', function (Blueprint $table): void {
            $table->index(
                ['filing_id', 'parser_version', 'parser_config_version'],
                'raw_facts_extraction_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('raw_facts', function (Blueprint $table): void {
            $table->dropIndex('raw_facts_extraction_idx');
            $table->dropColumn(['parser_version', 'parser_config_version']);
        });

        Schema::table('xbrl_dimensions', function (Blueprint $table): void {
            $table->dropIndex('xbrl_dimensions_extraction_idx');
            $table->dropColumn(['parser_version', 'parser_config_version']);
        });

        Schema::table('xbrl_units', function (Blueprint $table): void {
            $table->dropUnique('xbrl_units_extraction_identity_unique');
            $table->unique(['filing_id', 'source_unit_id']);
            $table->dropColumn(['parser_version', 'parser_config_version']);
        });

        Schema::table('xbrl_contexts', function (Blueprint $table): void {
            $table->dropUnique('xbrl_contexts_extraction_identity_unique');
            $table->unique(['filing_id', 'source_context_id']);
            $table->dropColumn(['parser_version', 'parser_config_version']);
        });
    }
};
