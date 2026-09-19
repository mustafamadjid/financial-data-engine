<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('da_artifact_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('artifact_type', 50);
            $table->string('artifact_version', 50);
            $table->string('source_path', 500);
            $table->string('source_sha256', 64);
            $table->unsignedInteger('row_count');
            $table->string('status', 30);
            $table->string('actor_id', 128)->nullable();
            $table->timestamps();
            $table->unique(['artifact_type', 'artifact_version', 'source_sha256'], 'da_artifact_import_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('da_artifact_imports');
    }
};
