<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_muestra_estadisticas_por_grupo_autenticado(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $asignado = EstadoActivo::firstOrCreate(
            ['Nombre' => 'Asignado (deployed)'],
            ['Grupo' => 'Asignado', 'Deployed' => true, 'Deployable' => false, 'Estado' => 1]
        );
        $disponible = EstadoActivo::firstOrCreate(
            ['Nombre' => 'Disponible (deployable)'],
            ['Grupo' => 'Disponible', 'Deployed' => false, 'Deployable' => true, 'Estado' => 1]
        );

        Activo::create(['Nombre_de_activo' => 'Equipo A', 'Etiqueta_activo' => 'TBDASH001', 'Estado' => 'Asignado', 'fk_estado' => $asignado->id]);
        Activo::create(['Nombre_de_activo' => 'Equipo B', 'Etiqueta_activo' => 'TBDASH002', 'Estado' => 'Asignado', 'fk_estado' => $asignado->id]);
        Activo::create(['Nombre_de_activo' => 'Equipo C', 'Etiqueta_activo' => 'TBDASH003', 'Estado' => 'Disponible', 'fk_estado' => $disponible->id]);

        $respuesta = $this->actingAs($admin)->get('/');

        $respuesta->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Asignado')
            ->assertSee('Disponible')
            ->assertSee('TBDASH001')
            ->assertSee('TBDASH003');
    }
}
