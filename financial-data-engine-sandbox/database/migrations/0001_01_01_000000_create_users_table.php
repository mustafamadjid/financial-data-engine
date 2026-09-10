<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Authentication tables were intentionally removed from new installs.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Authentication tables are removed by the dedicated forward migration.
    }
};
