<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('filing_id')
                ->constrained('filings')
                ->restrictOnDelete();

            $table->string('trigger', 50);
            $table->string('status', 30);

            $table->uuid('correlation_id')->unique();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['filing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
