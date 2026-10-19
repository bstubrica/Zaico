<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrar('admin', 'admin@ti.local', 'Administrador', 'Admin', 'Admin123!');
        $this->sembrar('soporte', 'soporte@ti.local', 'Soporte Técnico', 'SoporteTecnico', 'Soporte123!');
        $this->sembrar('auditor', 'auditor@ti.local', 'Auditor', 'Auditor', 'Auditor123!');
    }

    /**
     * `role` no es asignable en masa (defensa en profundidad), por lo que se
     * fija explícitamente con forceFill. La cuenta se localiza por `username`
     * (identificador de acceso) para no duplicarla si el correo se editó desde
     * el panel; el correo solo se fija al crear la cuenta.
     */
    private function sembrar(string $username, string $email, string $nombre, string $rol, string $password): void
    {
        $usuario = User::firstOrNew(['username' => $username], ['email' => $email]);

        $usuario->forceFill([
            'name' => $nombre,
            'role' => $rol,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();
    }
}
