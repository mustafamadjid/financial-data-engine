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
        Schema::create('pipeline_job_runs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pipeline_run_id');
            $table->string('filing_id', 128);

            $table->string('stage', 50);
            $table->string('job_class');
            $table->string('queue_name', 100)->default('default');

            $table->unsignedInteger('attempt')->default(1);

            $table->string('status', 30);

            $table->string('idempotency_key', 255);
            $table->uuid('correlation_id');

            // Error metadata
            $table->string('error_type')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();

            // Execution timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Indexes
             |--------------------------------------------------------------------------
             */

            $table->index('pipeline_run_id');
            $table->index('filing_id');
            $table->index('status');
            $table->index('correlation_id');

            $table->index(
                ['pipeline_run_id', 'stage'],
                'pipeline_job_runs_pipeline_stage_idx'
            );

            $table->index(
                ['filing_id', 'stage'],
                'pipeline_job_runs_filing_stage_idx'
            );

            /*
             |--------------------------------------------------------------------------
             | Idempotency / attempt uniqueness
             |--------------------------------------------------------------------------
             |
             | Satu logical operation dapat memiliki beberapa retry attempt.
             | Karena itu idempotency_key tidak dibuat UNIQUE sendirian.
             |
             */

            $table->unique(
                ['idempotency_key', 'attempt'],
                'pipeline_job_runs_idempotency_attempt_unique'
            );

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_job_runs');
    }
};
