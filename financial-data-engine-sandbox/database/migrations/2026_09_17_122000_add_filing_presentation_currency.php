<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            $table->string('presentation_currency', 3)->nullable()->after('taxonomy_entry_point');
        });
    }

    public function down(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            $table->dropColumn('presentation_currency');
        });
    }
};
