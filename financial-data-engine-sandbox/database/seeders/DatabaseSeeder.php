<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // No application authentication data is seeded.
        // DA validation rules are installed explicitly with ValidationRuleSeeder
        // after the DA artifact version has been approved for the environment.
    }
}
