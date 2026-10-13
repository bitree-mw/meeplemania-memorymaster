<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GamesSeeder::class);

        // Administrators are created with `php artisan games:create-admin`, never from the environment.
    }
}
