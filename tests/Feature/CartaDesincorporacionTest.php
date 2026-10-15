<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartaDesincorporacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private EstadoActivo $disponible;

    private EstadoActivo $desincorporado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->disponible = EstadoActivo::create([
            'Nombre' => 'Disponible (deployable)',
            'Grupo' => 'Disponible',
            'Deployed' => false,
            'Deployable' => true,
            'Estado' => 1,
        ]);
        $this->desincorporado = EstadoActivo::create([
            'Nombre' => 'Desincorporado (undeployable)',
            'Grupo' => 'Desincorporado',
            'Deployed' => false,
            'Deployable' => false,
            'Estado' => 1,
        ]);
    }

    private function crearActivo(string $etiqueta): Activo
    {
        return Activo::create([
            'Nombre_de_activo' => 'Equipo '.$etiqueta,
            'Etiqueta_activo' => $etiqueta,
            'Estado' => $this->disponible->Grupo,
            'fk_estado' => $this->disponible->id,
        ]);
    }

    private function desincorporar(Activo $activo, array $extra = []): Desincorporacion
    {
        $this->actingAs($this->admin)->post(route('activos.desincorporar', $activo), array_merge([
            'tipo' => 'Donacion',
            'destinatario' => 'Escuela local',
            'motivo' => 'Equipo en buen estado',
            'fecha' => now()->toDateString(),
        ], $extra));

        return Desincorporacion::where('fk_activo', $activo->id)->firstOrFail();
    }

    public function test_apartado_carta_requiere_autenticacion(): void
    {
        $this->get(route('desincorporados.carta'))->assertRedirect(route('login'));
        $activo = $this->crearActivo('TBCR100');
        $baja = $this->desincorporar($activo);
        auth()->logout();
        $this->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id]))->assertRedirect(route('login'));
    }

    public function test_carta_individual_puede_mostrarse_en_impresion(): void
    {
        $activo = $this->crearActivo('TBCR101');
        $baja = $this->desincorporar($activo, [
            'destinatario' => 'Colegio Central',
            'detalle_destino' => 'Asignado a laboratorio',
            'referencia' => 'ACTA-001',
            'motivo' => 'Baja por donación institucional',
        ]);

        $this->actingAs($this->admin)
            ->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id, 'formato' => 'impresion']))
            ->assertOk()
            ->assertSee('TBCR101')
            ->assertSee('Colegio Central')
            ->assertSee('ACTA-001')
            ->assertSee('Baja por donación institucional')
            ->assertSee('Donación');
    }

    public function test_carta_individual_marcar_anulada(): void
    {
        $activo = $this->crearActivo('TBCR102');
        $this->desincorporar($activo);

        $this->actingAs($this->admin)->post(route('activos.reincorporar', $activo));

        $baja = Desincorporacion::where('fk_activo', $activo->id)->firstOrFail();
        $this->assertSame('Anulada', $baja->fresh()->Estado);

        $this->actingAs($this->admin)
            ->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id, 'formato' => 'impresion']))
            ->assertOk()
            ->assertSee('ANULADA');
    }

    public function test_carta_acta_sin_filtros_retorna_documento(): void
    {
        $a1 = $this->crearActivo('TBCR200');
        $a2 = $this->crearActivo('TBCR201');
        $this->desincorporar($a1);
        $this->desincorporar($a2);

        $this->actingAs($this->admin)
            ->get(route('desincorporados.carta'))
            ->assertOk()
            ->assertSee('TBCR200')
            ->assertSee('TBCR201');
    }

    public function test_carta_acta_con_filtro_que_no_da_resultados_devuelve_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('desincorporados.carta', ['termino' => 'NO-EXISTE']))
            ->assertNotFound();
    }

    public function test_carta_pdf_devuelve_pdf(): void
    {
        $activo = $this->crearActivo('TBCR300');
        $baja = $this->desincorporar($activo);

        $response = $this->actingAs($this->admin)
            ->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id, 'formato' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_carta_xls_devuelve_vnd_ms_excel(): void
    {
        $activo = $this->crearActivo('TBCR400');
        $baja = $this->desincorporar($activo);

        $response = $this->actingAs($this->admin)
            ->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id, 'formato' => 'xls']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.ms-excel');
    }

    public function test_carta_xlsx_devuelve_xlsx(): void
    {
        $activo = $this->crearActivo('TBCR500');
        $baja = $this->desincorporar($activo);

        $response = $this->actingAs($this->admin)
            ->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id, 'formato' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_auditor_puede_generar_carta(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);
        $activo = $this->crearActivo('TBCR600');
        $baja = $this->desincorporar($activo);

        $this->actingAs($auditor)
            ->get(route('desincorporados.carta.activo', ['desincorporacion' => $baja->id, 'formato' => 'impresion']))
            ->assertOk();
    }
}
