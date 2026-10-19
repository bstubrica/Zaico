<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_el_correo_no_sirve_para_iniciar_sesion(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@ti.local',
            'password' => 'Admin123!',
        ]);

        $this->post('/login', [
            'email' => 'admin@ti.local',
            'password' => 'Admin123!',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_el_login_ignora_mayusculas_y_espacios_en_el_usuario(): void
    {
        $user = User::factory()->create([
            'username' => 'soporte',
            'email' => 'soporte@ti.local',
            'password' => 'Soporte123!',
        ]);

        $this->post('/login', [
            'username' => '  Soporte  ',
            'password' => 'Soporte123!',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
