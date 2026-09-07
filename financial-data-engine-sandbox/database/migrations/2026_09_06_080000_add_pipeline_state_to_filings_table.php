<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            $table->string('processing_stage', 30)->default('DISCOVERED')->after('downloaded_at');
            $table->string('quality_status', 30)->nullable()->after('processing_stage');
            $table->index('processing_stage');
            $table->index('quality_status');
        });
    }

    public function down(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            $table->dropIndex('filings_processing_stage_index');
            $table->dropIndex('filings_quality_status_index');
            $table->dropColumn(['processing_stage', 'quality_status']);
        });
    }
};
