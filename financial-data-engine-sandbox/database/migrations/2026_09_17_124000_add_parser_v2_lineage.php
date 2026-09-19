<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_facts', function (Blueprint $table): void {
            $table->string('source_element_id', 255)->nullable()->after('source_namespace');
            $table->index(['filing_id', 'source_element_id'], 'raw_facts_source_element_idx');
        });

        Schema::create('filing_taxonomy_metadata', function (Blueprint $table): void {
            $table->string('filing_id', 128)->primary();
            $table->string('parser_version', 100);
            $table->string('parser_config_version', 100);
            $table->string('contract_version', 20);
            $table->string('target_namespace', 500)->nullable();
            $table->json('imports');
            $table->json('import_locations');
            $table->json('linkbase_roles');
            $table->json('linkbase_references');
            $table->json('statement_families');
            $table->timestamp('extracted_at');
            $table->timestamps();
            $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filing_taxonomy_metadata');
        Schema::table('raw_facts', function (Blueprint $table): void {
            $table->dropIndex('raw_facts_source_element_idx');
            $table->dropColumn('source_element_id');
        });
    }
};
