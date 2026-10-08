<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@ti.local'], [
            'name' => 'Administrador', 'role' => 'Admin',
            'password' => Hash::make('Admin123!'), 'email_verified_at' => now(),
        ]);
        User::updateOrCreate(['email' => 'soporte@ti.local'], [
            'name' => 'Soporte Técnico', 'role' => 'SoporteTecnico',
            'password' => Hash::make('Soporte123!'), 'email_verified_at' => now(),
        ]);
        User::updateOrCreate(['email' => 'auditor@ti.local'], [
            'name' => 'Auditor', 'role' => 'Auditor',
            'password' => Hash::make('Auditor123!'), 'email_verified_at' => now(),
        ]);
    }
}
