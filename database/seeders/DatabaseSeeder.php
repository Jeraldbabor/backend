<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates a default admin user for initial access.
     * Change these credentials before deploying to production!
     */
    public function run(): void
    {
        // Create default admin user
        User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@campuseye.com',
            'password' => 'Rald@23', // Auto-hashed by the model's 'hashed' cast
            'role'     => 'admin',
        ]);
    }
}
