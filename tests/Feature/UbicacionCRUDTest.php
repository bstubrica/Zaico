<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UbicacionCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    public function test_admin_puede_registrar_ubicacion(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/ubicaciones', [
            'Nombre' => 'Piso 2 - Administración',
            'Descripcion' => 'Área administrativa',
            'Estado' => 'Activo',
        ]);

        $respuesta->assertRedirect('/ubicaciones');
        $this->assertDatabaseHas('UBICACIONES', ['Nombre' => 'Piso 2 - Administración', 'Estado' => 'Activo']);
    }

    public function test_nombre_duplicado_es_rechazado(): void
    {
        Ubicacion::create(['Nombre' => 'Bodega', 'Estado' => 'Activo']);

        $respuesta = $this->actingAs($this->admin)->post('/ubicaciones', [
            'Nombre' => 'Bodega',
        ]);

        $respuesta->assertSessionHasErrors('Nombre');
    }

    public function test_actualiza_una_ubicacion(): void
    {
        $ubicacion = Ubicacion::create(['Nombre' => 'Piso 1', 'Descripcion' => 'Vieja']);

        $respuesta = $this->actingAs($this->admin)->put("/ubicaciones/{$ubicacion->id}", [
            'Nombre' => 'Piso 1 - Soporte',
            'Descripcion' => 'Nueva',
            'Estado' => 'Activo',
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('UBICACIONES', [
            'id' => $ubicacion->id,
            'Nombre' => 'Piso 1 - Soporte',
            'Descripcion' => 'Nueva',
        ]);
    }

    public function test_la_baja_es_logica_y_conserva_la_fila(): void
    {
        $ubicacion = Ubicacion::create(['Nombre' => 'Almacén', 'Estado' => 'Activo']);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo en almacén',
            'Etiqueta_activo' => 'TBUBI0001',
            'Estado' => 'Disponible',
            'Ubicacion' => 'Almacén',
            'fk_ubicacion' => $ubicacion->id,
        ]);

        $this->actingAs($this->admin)->delete("/ubicaciones/{$ubicacion->id}")
            ->assertRedirect('/ubicaciones');

        $this->assertDatabaseHas('UBICACIONES', ['id' => $ubicacion->id, 'Estado' => 'Inactivo']);
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'fk_ubicacion' => $ubicacion->id]);
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->post('/ubicaciones', ['Nombre' => 'X'])->assertForbidden();
    }

    public function test_sin_sesion_redirige_a_login(): void
    {
        $this->get('/ubicaciones')->assertRedirect(route('login'));
    }

    public function test_auditor_no_puede_consultar_el_catalogo(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get('/ubicaciones')->assertForbidden();
    }
}
