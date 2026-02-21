<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::updateOrCreate(
            ['email' => 'superadmin@campuseye.com'],
            [
                'name' => 'Super Admin',
                'password' => 'Rald@23', // Auto-hashed
                'role' => 'superadmin',
            ]
        );
    }
}
