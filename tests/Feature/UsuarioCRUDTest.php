<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    public function test_admin_puede_crear_usuario_con_rol(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Nuevo Técnico',
            'email' => 'tecnico@zaico.test',
            'password' => 'contrasena-segura',
            'role' => 'SoporteTecnico',
        ]);

        $respuesta->assertSessionHasNoErrors()
            ->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Nuevo Técnico',
            'email' => 'tecnico@zaico.test',
            'role' => 'SoporteTecnico',
        ]);

        $usuario = User::where('email', 'tecnico@zaico.test')->firstOrFail();
        $this->assertTrue(Hash::check('contrasena-segura', $usuario->password));
    }

    public function test_crear_usuario_con_correo_duplicado_es_rechazado(): void
    {
        User::factory()->create(['email' => 'duplicado@zaico.test']);

        $this->actingAs($this->admin)
            ->post(route('usuarios.store'), [
                'name' => 'X',
                'email' => 'duplicado@zaico.test',
                'password' => 'contrasena-segura',
                'role' => 'Auditor',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_rol_de_creacion_es_obligatorio_y_debe_ser_valido(): void
    {
        $this->actingAs($this->admin)
            ->post(route('usuarios.store'), [
                'name' => 'X',
                'email' => 'x@zaico.test',
                'password' => 'contrasena-segura',
                'role' => 'SuperAdmin',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_admin_puede_editar_usuario_y_cambiarle_el_rol(): void
    {
        $usuario = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), [
            'name' => 'Nombre cambiado',
            'email' => 'cambiado@zaico.test',
            'role' => 'Admin',
            'password' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $usuario->refresh();
        $this->assertSame('Nombre cambiado', $usuario->name);
        $this->assertSame('Admin', $usuario->role);
    }

    public function test_editar_usuario_mantiene_dueno_y_puede_renovar_contrasena(): void
    {
        $usuario = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($this->admin)->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'role' => 'Auditor',
            'password' => 'nueva-contrasena',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nueva-contrasena', $usuario->fresh()->password));
    }

    public function test_admin_puede_eliminar_usuario_sin_registros_asociados(): void
    {
        $usuario = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($this->admin)
            ->delete(route('usuarios.destroy', $usuario))
            ->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseMissing('users', ['id' => $usuario->id]);
    }

    public function test_no_se_puede_eliminar_usuario_con_registros_asociados(): void
    {
        $usuario = User::factory()->create(['role' => 'Admin']);
        $personal = Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Perez',
            'Nombre_usuario' => 'anaperez',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBMNT0001',
            'Estado' => 'Asignado',
        ]);
        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $personal->id,
            'fk_usuario' => $usuario->id,
            'Fecha_asignacion' => '2026-10-01',
            'Estado' => 'Activa',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('usuarios.destroy', $usuario))
            ->assertStatus(409);

        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
    }

    public function test_admin_no_puede_eliminarse_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('usuarios.destroy', $this->admin))
            ->assertStatus(409);

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_solo_admin_accede_a_la_administracion(): void
    {
        $soporte = User::factory()->create(['role' => 'SoporteTecnico']);

        $this->actingAs($soporte)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($soporte)->get(route('usuarios.create'))->assertForbidden();
        $this->actingAs($soporte)
            ->post(route('usuarios.store'), [
                'name' => 'X',
                'email' => 'x@zaico.test',
                'password' => 'contrasena-segura',
                'role' => 'Auditor',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)->get(route('usuarios.index'))->assertOk()->assertSee('Nuevo usuario');
    }

    public function test_gestion_de_usuarios_requiere_sesion(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
        $this->get(route('usuarios.create'))->assertRedirect(route('login'));
        $this->post(route('usuarios.store'), ['name' => 'X', 'email' => 'x@zaico.test', 'password' => 'a', 'role' => 'Auditor'])
            ->assertRedirect(route('login'));
    }
}
