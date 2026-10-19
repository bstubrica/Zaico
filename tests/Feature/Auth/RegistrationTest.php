<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_registro_publico_esta_deshabilitado_por_defecto(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_no_se_puede_registrar_por_defecto(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'test.user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_al_habilitar_el_registro_las_cuentas_reciben_rol_de_solo_lectura(): void
    {
        config(['zaico.registro_publico' => true]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'Test.User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'username' => 'test.user',
            'email' => 'test@example.com',
            'role' => 'Auditor',
        ]);
    }
}
