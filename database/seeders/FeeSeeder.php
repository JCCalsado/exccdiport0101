<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * FeeSeeder — DISABLED
 *
 * Fee Management has been removed. This seeder is kept as a stub
 * to prevent autoload errors. It is NOT called from DatabaseSeeder.
 */
class FeeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('FeeSeeder is disabled — Fee Management has been removed.');
    }
}