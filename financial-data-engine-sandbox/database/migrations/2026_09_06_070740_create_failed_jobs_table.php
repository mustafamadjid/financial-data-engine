<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The framework jobs migration already creates this table.
    }

    public function down(): void
    {
        // Keep the table owned by the framework jobs migration.
    }
};
