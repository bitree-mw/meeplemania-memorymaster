<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GamesSeeder::class);

        // Optional first administrator, configured through the environment.
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if ($email && $password && ! User::where('email', $email)->exists()) {
            User::create([
                'username' => env('ADMIN_USERNAME', 'admin'),
                'email' => $email,
                'password' => $password,
            ])->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();
        }
    }
}
