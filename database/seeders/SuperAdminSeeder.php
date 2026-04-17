<?php

namespace Database\Seeders;

use App\Models\Users;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed a default super admin account.
     */
    public function run(): void
    {
        Users::updateOrCreate(
            ['email' => 'superadmin@ukproject.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'username' => 'superadmin',
                'role' => 'superadmin',
                'is_client' => false,
                'is_coach' => false,
                'password' => Hash::make('Admin@12345'),
                'email_verified_at' => now(),
            ]
        );
    }
}
