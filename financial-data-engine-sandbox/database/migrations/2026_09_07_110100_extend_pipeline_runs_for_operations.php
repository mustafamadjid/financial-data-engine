<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->string('started_from_stage', 50)->nullable()->after('trigger');
            $table->string('initiated_by', 128)->nullable()->after('status');
            $table->text('reason')->nullable()->after('initiated_by');
            $table->json('dependency_versions')->nullable()->after('reason');
            $table->index(['filing_id', 'started_from_stage', 'status'], 'pipeline_runs_reprocess_stage_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->dropIndex('pipeline_runs_reprocess_stage_status_idx');
            $table->dropColumn(['started_from_stage', 'initiated_by', 'reason', 'dependency_versions']);
        });
    }
};
