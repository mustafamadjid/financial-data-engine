<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_job_runs', function (Blueprint $table): void {
            $table->string('failure_classification', 30)->nullable()->after('error_context');
            $table->uuid('failed_job_uuid')->nullable()->after('failure_classification');
            $table->string('logical_input_hash', 64)->nullable()->after('failed_job_uuid');
            $table->index(['filing_id', 'stage', 'failure_classification'], 'pipeline_job_runs_retryability_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_job_runs', function (Blueprint $table): void {
            $table->dropIndex('pipeline_job_runs_retryability_idx');
            $table->dropColumn(['failure_classification', 'failed_job_uuid', 'logical_input_hash']);
        });
    }
};
