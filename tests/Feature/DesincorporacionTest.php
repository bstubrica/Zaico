<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesincorporacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    private function estado(string $nombre, string $grupo, bool $deployed = false): EstadoActivo
    {
        return EstadoActivo::create([
            'Nombre' => $nombre,
            'Grupo' => $grupo,
            'Deployed' => $deployed,
            'Deployable' => $grupo === 'Disponible',
            'Estado' => 1,
        ]);
    }

    private function activo(string $etiqueta, EstadoActivo $estado): Activo
    {
        return Activo::create([
            'Nombre_de_activo' => 'Equipo '.$etiqueta,
            'Etiqueta_activo' => $etiqueta,
            'Estado' => $estado->Grupo,
            'fk_estado' => $estado->id,
        ]);
    }

    private function desincorporar(Activo $activo, array $extra = []): void
    {
        $this->estado('Desincorporado (undeployable)', 'Desincorporado', true);

        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar', $activo), array_merge([
                'tipo' => 'Donacion',
                'destinatario' => 'Escuela local',
                'motivo' => 'Equipo en buen estado',
                'fecha' => now()->toDateString(),
            ], $extra));
    }

    public function test_apartado_requiere_autenticacion(): void
    {
        $this->get(route('desincorporados.index'))->assertRedirect(route('login'));
    }

    public function test_cualquier_usuario_autenticado_ve_el_apartado(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get(route('desincorporados.index'))->assertOk();
    }

    public function test_el_desincorporado_deja_de_aparecer_en_el_inventario_activo(): void
    {
        $disponible = $this->estado('Disponible (deployable)', 'Disponible');
        $activo = $this->activo('TBDES100', $disponible);

        $this->desincorporar($activo);

        $this->actingAs($this->admin)
            ->get(route('activos.index', ['termino' => 'TBDES100']))
            ->assertOk()
            ->assertDontSee('Equipo TBDES100');

        $this->actingAs($this->admin)
            ->get(route('desincorporados.index', ['termino' => 'TBDES100']))
            ->assertOk()
            ->assertSee('Equipo TBDES100');
    }

    public function test_una_venta_requiere_monto(): void
    {
        $disponible = $this->estado('Disponible (deployable)', 'Disponible');
        $activo = $this->activo('TBDES101', $disponible);
        $this->estado('Desincorporado (undeployable)', 'Desincorporado', true);

        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar', $activo), [
                'tipo' => 'Venta',
                'destinatario' => 'Comprador',
                'motivo' => 'Venta',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('monto');

        $this->assertSame('Disponible', $activo->fresh()->Estado);
    }

    public function test_admin_puede_editar_el_registro_de_baja(): void
    {
        $disponible = $this->estado('Disponible (deployable)', 'Disponible');
        $activo = $this->activo('TBDES101', $disponible);
        $this->desincorporar($activo);

        $baja = Desincorporacion::where('fk_activo', $activo->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('desincorporados.update', $baja), [
                'tipo' => 'Venta',
                'destinatario' => 'Proveedor ACME',
                'referencia' => 'FAC-001',
                'monto' => 250.75,
                'moneda' => 'USD',
                'motivo' => 'Venta de equipos obsoletos',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('DESINCORPORACIONES', [
            'id' => $baja->id,
            'Tipo' => 'Venta',
            'Destinatario' => 'Proveedor ACME',
        ]);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $baja->fk_activo,
            'Tipo_evento' => 'Modificacion',
        ]);
    }

    public function test_auditor_no_puede_editar_ni_reincorporar(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);
        $disponible = $this->estado('Disponible (deployable)', 'Disponible');
        $activo = $this->activo('TBDES200', $disponible);
        $this->desincorporar($activo);
        $baja = Desincorporacion::where('fk_activo', $activo->id)->firstOrFail();

        $this->actingAs($auditor)
            ->put(route('desincorporados.update', $baja), [
                'tipo' => 'Donacion',
                'destinatario' => 'Escuela',
                'motivo' => 'x',
                'fecha' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->post(route('activos.reincorporar', $activo))
            ->assertForbidden();
    }

    public function test_admin_puede_reincorporar_un_equipo(): void
    {
        $disponible = $this->estado('Disponible (deployable)', 'Disponible');
        $activo = $this->activo('TBDES201', $disponible);
        $this->desincorporar($activo);

        $this->actingAs($this->admin)
            ->post(route('activos.reincorporar', $activo))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $activo->refresh();
        $this->assertSame('Disponible', $activo->Estado);
        $this->assertSame($disponible->id, $activo->fk_estado);
        $this->assertDatabaseHas('DESINCORPORACIONES', [
            'fk_activo' => $activo->id,
            'Estado' => 'Anulada',
        ]);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $activo->id,
            'Tipo_evento' => 'Reincorporacion',
        ]);
    }
}
