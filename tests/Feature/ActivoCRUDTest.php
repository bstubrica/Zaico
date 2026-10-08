<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Personal;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivoCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->seed(EstadoSeeder::class);
    }

    public function test_puede_registrar_activo(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop Dell Latitude 5520',
            'Etiqueta_activo' => 'TBCOR0999',
            'Serial' => 'DL-5520-001',
            'Estado' => 'Disponible',
        ]);

        $respuesta->assertRedirect('/activos');
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBCOR0999']);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', ['Tipo_evento' => 'Creacion']);
    }

    public function test_etiqueta_duplicada_es_rechazada(): void
    {
        Activo::create(['Nombre_de_activo' => 'X', 'Etiqueta_activo' => 'DUP001', 'Estado' => 'Activo']);

        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Y',
            'Etiqueta_activo' => 'DUP001',
            'Estado' => 'Activo',
        ]);

        $respuesta->assertSessionHasErrors('Etiqueta_activo');
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);
        $activo = Activo::create([
            'Nombre_de_activo' => 'PC Auditoria',
            'Etiqueta_activo' => 'AUD001',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($auditor)
            ->put("/activos/{$activo->id}/estado", ['fk_estado' => 1, 'motivo' => 'x'])
            ->assertForbidden();
    }

    public function test_sin_sesion_redirige_a_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_auditor_puede_consultar_listado(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get('/activos')->assertOk();
    }

    public function test_admin_puede_asignar_y_devolver_activo(): void
    {
        $persona = Personal::create([
            'Nombre' => 'Maria',
            'Apellido' => 'Perez',
            'Nombre_usuario' => 'mperez',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop HP',
            'Etiqueta_activo' => 'TBASG0001',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)->post("/activos/{$activo->id}/asignacion", [
            'fk_Personal' => $persona->id,
            'Fecha_asignacion' => '2026-10-08',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ASIGNACIONES', ['fk_Activo' => $activo->id, 'Estado' => 'Activa']);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', ['fk_activo' => $activo->id, 'Tipo_evento' => 'Asignacion']);
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'Estado' => 'Asignado']);

        $this->actingAs($this->admin)->post("/activos/{$activo->id}/devolucion", [
            'Fecha_devolucion' => '2026-10-09',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ASIGNACIONES', ['fk_Activo' => $activo->id, 'Estado' => 'Cerrada']);
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'Estado' => 'Disponible']);
    }

    public function test_no_se_puede_asignar_un_activo_ya_asignado(): void
    {
        $persona = Personal::create([
            'Nombre' => 'Juan',
            'Apellido' => 'Garcia',
            'Nombre_usuario' => 'jgarcia',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Monitor Samsung',
            'Etiqueta_activo' => 'TBASG0002',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)->post("/activos/{$activo->id}/asignacion", [
            'fk_Personal' => $persona->id,
            'Fecha_asignacion' => '2026-10-08',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post("/activos/{$activo->id}/asignacion", [
            'fk_Personal' => $persona->id,
            'Fecha_asignacion' => '2026-10-10',
        ])->assertSessionHasErrors('asignacion');
    }

    public function test_eventos_solo_admin_y_auditor(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Notebook Lenovo',
            'Etiqueta_activo' => 'TBEVT0001',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/activos/{$activo->id}/eventos")
            ->assertOk();

        $soporte = User::factory()->create(['role' => 'SoporteTecnico']);

        $this->actingAs($soporte)
            ->getJson("/activos/{$activo->id}/eventos")
            ->assertForbidden();
    }
}
