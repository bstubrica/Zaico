<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_no_es_asignable_en_masa(): void
    {
        $usuario = User::create([
            'name' => 'Intruso',
            'email' => 'intruso@ti.local',
            'password' => 'Secret123!',
            'role' => 'Admin',
        ]);

        $this->assertSame('Auditor', $usuario->fresh()->role);
    }

    public function test_el_seeder_asigna_roles_aunque_no_sean_fillable(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@ti.local', 'role' => 'Admin']);
        $this->assertDatabaseHas('users', ['email' => 'soporte@ti.local', 'role' => 'SoporteTecnico']);
        $this->assertDatabaseHas('users', ['email' => 'auditor@ti.local', 'role' => 'Auditor']);
    }
}
