<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command?->info('No se crean usuarios de ejemplo. Para una instalación vacía utiliza php artisan prosec:inicializar.');
    }
}
