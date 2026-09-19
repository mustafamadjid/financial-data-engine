<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('normalized_facts', function (Blueprint $table): void {
            $table->string('normalized_dataset_version', 128)->nullable()->after('normalization_version');
            $table->index(['filing_id', 'normalized_dataset_version'], 'normalized_facts_dataset_idx');
        });
    }

    public function down(): void
    {
        Schema::table('normalized_facts', function (Blueprint $table): void {
            $table->dropIndex('normalized_facts_dataset_idx');
            $table->dropColumn('normalized_dataset_version');
        });
    }
};
