<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('published_snapshots', function (Blueprint $table): void {
            $table->string('snapshot_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->unsignedInteger('revision_number');
            $table->string('publish_contract_version', 128);
            $table->string('normalized_dataset_version', 128);
            $table->string('validation_rule_set_version', 128);
            $table->string('publish_idempotency_key', 255)->unique();
            $table->json('payload');
            $table->json('lineage');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->index(['filing_id', 'revision_number']);
            $table->index('publish_contract_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_snapshots');
    }
};
