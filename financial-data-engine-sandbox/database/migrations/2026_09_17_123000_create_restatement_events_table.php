<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restatement_events', function (Blueprint $table): void {
            $table->id();
            $table->string('filing_id', 128);
            $table->string('superseded_filing_id', 128);
            $table->string('canonical_concept', 100);
            $table->string('current_normalized_fact_id', 128)->nullable();
            $table->string('prior_normalized_fact_id', 128)->nullable();
            $table->decimal('current_value', 38, 18)->nullable();
            $table->decimal('prior_value', 38, 18)->nullable();
            $table->string('rule_code', 20)->default('CRX-001');
            $table->unsignedInteger('rule_version')->default(1);
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->unique(['filing_id', 'superseded_filing_id', 'canonical_concept'], 'restatement_event_identity');
            $table->index(['filing_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restatement_events');
    }
};
