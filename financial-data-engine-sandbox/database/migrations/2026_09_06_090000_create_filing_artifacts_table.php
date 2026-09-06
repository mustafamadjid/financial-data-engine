<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filing_artifacts', function (Blueprint $table): void {
            $table->string('artifact_id', 128)->primary();
            $table->string('filing_id', 128);
            $table->string('artifact_type', 50);
            $table->string('source_hash', 64);
            $table->text('storage_path');
            $table->string('original_filename', 255);
            $table->string('content_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->dateTimeTz('downloaded_at');
            $table->timestamps();

            $table->foreign('filing_id')
                ->references('filing_id')
                ->on('filings')
                ->restrictOnDelete();
            $table->unique(['filing_id', 'artifact_type', 'source_hash'], 'filing_artifacts_filing_type_hash_unique');
            $table->index(['filing_id', 'artifact_type']);
            $table->index('source_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filing_artifacts');
    }
};
