<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('services.admin_seed.email');
        $password = config('services.admin_seed.password');

        if (! $email || ! $password) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('services.admin_seed.name'),
                'password' => $password,
                'email_verified_at' => now(),
                'active' => true,
            ],
        );
    }
}
