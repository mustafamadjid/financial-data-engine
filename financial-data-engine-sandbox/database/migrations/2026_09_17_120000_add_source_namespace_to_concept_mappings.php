<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concept_mappings', function (Blueprint $table): void {
            $table->string('source_namespace', 255)->nullable()->after('source_concept');
            $table->string('dimension_policy', 50)->default('UNDIMENSIONED_ONLY')->after('period_type');
            $table->index(['source_namespace', 'source_concept'], 'concept_mappings_namespace_concept_idx');
        });
    }

    public function down(): void
    {
        Schema::table('concept_mappings', function (Blueprint $table): void {
            $table->dropIndex('concept_mappings_namespace_concept_idx');
            $table->dropColumn('source_namespace');
            $table->dropColumn('dimension_policy');
        });
    }
};
