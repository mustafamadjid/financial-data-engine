<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('normalized_facts', function (Blueprint $table): void {
            $table->string('availability_status', 30)->default('UNKNOWN')->after('value');
            $table->index('availability_status');
        });
    }

    public function down(): void
    {
        Schema::table('normalized_facts', function (Blueprint $table): void {
            $table->dropIndex(['availability_status']);
            $table->dropColumn('availability_status');
        });
    }
};
