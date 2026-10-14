<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadoCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    public function test_admin_puede_registrar_estado(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/estados', [
            'Nombre' => 'Prestado (deployable)',
            'Grupo' => 'Asignado',
            'Deployed' => '0',
            'Deployable' => '1',
        ]);

        $respuesta->assertRedirect('/estados');
        $this->assertDatabaseHas('ESTADOS_ACTIVO', [
            'Nombre' => 'Prestado (deployable)',
            'Grupo' => 'Asignado',
            'Deployable' => true,
            'Estado' => 1,
        ]);
    }

    public function test_nombre_duplicado_es_rechazado(): void
    {
        EstadoActivo::create(['Nombre' => 'Asignado (deployed)', 'Grupo' => 'Asignado']);

        $respuesta = $this->actingAs($this->admin)->post('/estados', [
            'Nombre' => 'Asignado (deployed)',
            'Grupo' => 'Asignado',
        ]);

        $respuesta->assertSessionHasErrors('Nombre');
    }

    public function test_grupo_invalido_es_rechazado(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/estados', [
            'Nombre' => 'Estado raro',
            'Grupo' => 'Inventado',
        ]);

        $respuesta->assertSessionHasErrors('Grupo');
    }

    public function test_actualiza_un_estado(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Disponible (deployed)', 'Grupo' => 'Disponible']);

        $respuesta = $this->actingAs($this->admin)->put("/estados/{$estado->id}", [
            'Nombre' => 'Disponible (deployed)',
            'Grupo' => 'Disponible',
            'Deployed' => '0',
            'Deployable' => '1',
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ESTADOS_ACTIVO', [
            'id' => $estado->id,
            'Deployable' => true,
            'Deployed' => false,
        ]);
    }

    public function test_la_eliminacion_es_logica_y_conserva_la_fila(): void
    {
        $estado = EstadoActivo::create([
            'Nombre' => 'Defectuoso (pending)',
            'Grupo' => 'Defectuoso',
            'Deployed' => true,
            'Estado' => 1,
        ]);

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop defectuoso',
            'Etiqueta_activo' => 'TBEST0001',
            'Estado' => 'Defectuoso',
            'fk_estado' => $estado->id,
        ]);

        $this->actingAs($this->admin)->delete("/estados/{$estado->id}")
            ->assertRedirect('/estados');

        $this->assertDatabaseHas('ESTADOS_ACTIVO', ['id' => $estado->id, 'Estado' => 0]);
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'fk_estado' => $estado->id]);
    }

    public function test_el_estado_eliminado_desaparece_del_catalogo(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Reparacion (pending)', 'Grupo' => 'Reparacion', 'Estado' => 1]);

        $this->actingAs($this->admin)->delete("/estados/{$estado->id}");

        $respuesta = $this->actingAs($this->admin)->getJson('/estados');
        $respuesta->assertOk();
        $this->assertCount(0, $respuesta->json());
    }

    public function test_formularios_de_alta_y_edicion_renderizan(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Disponible (deployable)', 'Grupo' => 'Disponible']);

        $this->actingAs($this->admin)->get('/estados/create')->assertOk();
        $this->actingAs($this->admin)->get("/estados/{$estado->id}/edit")->assertOk();
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->post('/estados', [
            'Nombre' => 'X',
            'Grupo' => 'Disponible',
        ])->assertForbidden();
    }

    public function test_sin_sesion_redirige_a_login(): void
    {
        $this->get('/estados/create')->assertRedirect(route('login'));
    }

    public function test_auditor_puede_consultar_el_catalogo(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get('/estados')->assertOk();
    }
}
