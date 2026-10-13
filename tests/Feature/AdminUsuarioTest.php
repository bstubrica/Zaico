<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function datos(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@ti.local',
            'role' => 'SoporteTecnico',
            'password' => 'Contrasena123!',
            'password_confirmation' => 'Contrasena123!',
        ], $overrides);
    }

    public function test_admin_ve_el_listado_de_usuarios(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('Usuarios del sistema')
            ->assertSee($this->admin->name);
    }

    public function test_solo_admin_accede_a_la_gestion_de_usuarios(): void
    {
        foreach (['SoporteTecnico', 'Auditor'] as $rol) {
            $usuario = User::factory()->create(['role' => $rol]);

            $this->actingAs($usuario)->get(route('admin.usuarios.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('admin.usuarios.create'))->assertForbidden();
            $this->actingAs($usuario)->post(route('admin.usuarios.store'), $this->datos())->assertForbidden();
            $this->actingAs($usuario)->post(route('admin.usuarios.toggle', $this->admin))->assertForbidden();
        }
    }

    public function test_admin_crea_un_usuario(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.usuarios.index'));

        $creado = User::where('email', 'nuevo@ti.local')->firstOrFail();

        $this->assertSame('Nuevo Usuario', $creado->name);
        $this->assertSame('SoporteTecnico', $creado->role);
        $this->assertTrue($creado->estaActivo());
        $this->assertTrue(Hash::check('Contrasena123!', $creado->password));
    }

    public function test_no_permite_correo_duplicado(): void
    {
        User::factory()->create(['email' => 'repetido@ti.local']);

        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.create'))
            ->post(route('admin.usuarios.store'), $this->datos(['email' => 'repetido@ti.local']))
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('admin.usuarios.create'));

        $this->assertSame(1, User::where('email', 'repetido@ti.local')->count());
    }

    public function test_valida_rol_y_confirmacion_de_contrasena(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.create'))
            ->post(route('admin.usuarios.store'), $this->datos([
                'role' => 'SuperUsuario',
                'password_confirmation' => 'otra-cosa',
            ]))
            ->assertSessionHasErrors(['role', 'password']);
    }

    public function test_admin_actualiza_datos_y_rol(): void
    {
        $usuario = User::factory()->create(['role' => 'Auditor', 'name' => 'Viejo Nombre']);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $usuario), $this->datos([
                'name' => 'Nombre Nuevo',
                'email' => 'actualizado@ti.local',
                'role' => 'SoporteTecnico',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.usuarios.index'));

        $usuario->refresh();
        $this->assertSame('Nombre Nuevo', $usuario->name);
        $this->assertSame('actualizado@ti.local', $usuario->email);
        $this->assertSame('SoporteTecnico', $usuario->role);
    }

    public function test_actualizar_sin_contrasena_conserva_la_actual(): void
    {
        $usuario = User::factory()->create(['role' => 'Auditor']);
        $hashOriginal = $usuario->password;

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $usuario), $this->datos([
                'email' => $usuario->email,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($hashOriginal, $usuario->refresh()->password);
    }

    public function test_admin_puede_cambiar_la_contrasena(): void
    {
        $usuario = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $usuario), $this->datos([
                'email' => $usuario->email,
                'password' => 'NuevaClave456!',
                'password_confirmation' => 'NuevaClave456!',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave456!', $usuario->refresh()->password));
    }

    public function test_admin_no_puede_quitarse_su_propio_rol(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.edit', $this->admin))
            ->put(route('admin.usuarios.update', $this->admin), $this->datos([
                'email' => $this->admin->email,
                'role' => 'Auditor',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertSame('Admin', $this->admin->refresh()->role);
    }

    public function test_admin_no_puede_deshabilitar_su_propia_cuenta(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.index'))
            ->post(route('admin.usuarios.toggle', $this->admin))
            ->assertSessionHasErrors('usuario');

        $this->assertTrue($this->admin->refresh()->estaActivo());
    }

    public function test_deshabilita_y_habilita_a_otro_usuario(): void
    {
        $usuario = User::factory()->create(['role' => 'SoporteTecnico']);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.toggle', $usuario))
            ->assertSessionHasNoErrors();

        $this->assertFalse($usuario->refresh()->estaActivo());

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.toggle', $usuario))
            ->assertSessionHasNoErrors();

        $this->assertTrue($usuario->refresh()->estaActivo());
    }

    public function test_servicio_impide_dejar_el_sistema_sin_admin_activo(): void
    {
        $servicio = app(UsuarioService::class);

        $this->expectException(RuntimeException::class);
        $servicio->cambiarActivo($this->admin, false);
    }

    public function test_usuario_deshabilitado_no_puede_iniciar_sesion(): void
    {
        User::factory()->create([
            'email' => 'inactivo@ti.local',
            'password' => 'password',
            'activo' => false,
        ]);

        $this->post('/login', [
            'email' => 'inactivo@ti.local',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_cambio_desde_el_panel_se_guarda_y_permite_el_login(): void
    {
        $soporte = User::factory()->create([
            'name' => 'Soporte Técnico',
            'email' => 'soporte@ti.local',
            'role' => 'SoporteTecnico',
            'password' => 'Soporte123!',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $soporte), [
                'name' => 'Soporte Técnico',
                'email' => 'soportetic@tubrica.com',
                'role' => 'SoporteTecnico',
                'password' => 'NuevaClave456!',
                'password_confirmation' => 'NuevaClave456!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.usuarios.index'));

        $soporte->refresh();
        $this->assertSame('soportetic@tubrica.com', $soporte->email);
        $this->assertTrue(Hash::check('NuevaClave456!', $soporte->password));

        // Cierra la sesión del administrador y entra con las credenciales nuevas.
        Auth::logout();

        $this->post('/login', [
            'email' => 'soportetic@tubrica.com',
            'password' => 'NuevaClave456!',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($soporte);

        // Las credenciales anteriores ya no son válidas.
        Auth::logout();

        $this->post('/login', [
            'email' => 'soporte@ti.local',
            'password' => 'Soporte123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_login_ignora_mayusculas_y_espacios_en_el_correo(): void
    {
        $usuario = User::factory()->create([
            'email' => 'soporte@ti.local',
            'password' => 'Soporte123!',
        ]);

        $this->post('/login', [
            'email' => '  Soporte@TI.Local  ',
            'password' => 'Soporte123!',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_sesion_de_usuario_deshabilitado_se_cierra(): void
    {
        $inactivo = User::factory()->create(['role' => 'Auditor', 'activo' => false]);

        $this->actingAs($inactivo)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
