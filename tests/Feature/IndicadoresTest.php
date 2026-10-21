<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Compra;
use App\Models\Desincorporacion;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use App\Services\IndicadoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicadoresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function activo(string $etiqueta, array $extra = []): Activo
    {
        return Activo::create(array_merge([
            'Nombre_de_activo' => 'Equipo '.$etiqueta,
            'Etiqueta_activo' => $etiqueta,
            'Categoria' => 'Laptop',
            'Estado' => 'Disponible',
        ], $extra));
    }

    public function test_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('indicadores.index'))->assertRedirect(route('login'));
    }

    public function test_cualquier_rol_autenticado_puede_ver_la_pantalla(): void
    {
        foreach (['SoporteTecnico', 'Auditor'] as $rol) {
            $usuario = User::factory()->create(['role' => $rol]);

            $this->actingAs($usuario)->get(route('indicadores.index'))->assertOk();
        }
    }

    public function test_los_kpis_reflejan_el_inventario(): void
    {
        $disponible = $this->activo('TBKPI01');
        $disponible2 = $this->activo('TBKPI02');
        $asignado = $this->activo('TBKPI03', ['Estado' => 'Asignado']);
        $deBaja = $this->activo('TBKPI04', ['Estado' => 'Desincorporado']);

        $persona = Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'López',
            'Nombre_usuario' => 'alopez',
            'Estado' => 'Activo',
        ]);

        Asignacion::create([
            'fk_Activo' => $asignado->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->admin->id,
            'Fecha_asignacion' => now()->toDateString(),
            'Estado' => 'Activa',
        ]);

        Compra::create(['fk_activo' => $disponible->id, 'Fecha_Compra' => now()->subMonths(6), 'Costo_compra' => 100, 'Tiempo_garantia' => '24 meses']);
        Compra::create(['fk_activo' => $disponible2->id, 'Fecha_Compra' => now()->subMonths(6), 'Costo_compra' => 250, 'Tiempo_garantia' => '24 meses']);

        Mantenimiento::create([
            'fk_activo' => $disponible->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Limpieza',
            'Fecha_mantenimiento' => now()->subMonth()->toDateString(),
            'fk_usuario' => $this->admin->id,
        ]);

        Desincorporacion::create([
            'fk_activo' => $deBaja->id,
            'Tipo' => 'Donacion',
            'Destinatario' => 'Escuela local',
            'Motivo' => 'Equipo en buen estado',
            'Fecha' => now()->subWeek()->toDateString(),
            'Estado' => 'Vigente',
            'fk_usuario' => $this->admin->id,
        ]);

        $filtros = app(IndicadoresService::class)->normalizarFiltros([]);
        $kpis = app(IndicadoresService::class)->kpis($filtros);

        $this->assertSame(4, $kpis['equipos']);
        $this->assertSame(1, $kpis['asignados']);
        // Denominador sin el desincorporado: 1 de 3 activos vigentes.
        $this->assertSame(33.3, $kpis['utilizacion']);
        $this->assertSame(2, $kpis['disponibles']);
        $this->assertSame(350.0, $kpis['costo_total']);
        $this->assertSame(1, $kpis['bajas']);
        $this->assertSame(1, $kpis['mantenimientos']);
        // Las 4 altas caen en la ventana por defecto (últimos 12 meses).
        $this->assertSame(4, $kpis['altas']);
    }

    public function test_el_filtro_de_categoria_acota_stock_y_movimientos(): void
    {
        $this->activo('TBFIL01', ['Categoria' => 'Laptop']);
        $otro = $this->activo('TBFIL02', ['Categoria' => 'Monitor']);

        Compra::create(['fk_activo' => $otro->id, 'Fecha_Compra' => now(), 'Costo_compra' => 999, 'Tiempo_garantia' => '12 meses']);

        $filtros = app(IndicadoresService::class)->normalizarFiltros(['categoria' => 'Laptop']);
        $kpis = app(IndicadoresService::class)->kpis($filtros);

        $this->assertSame(1, $kpis['equipos']);
        $this->assertSame(0.0, $kpis['costo_total']);
    }

    public function test_las_garantias_comparten_la_ventana_de_las_alertas(): void
    {
        $porVencer = $this->activo('TBGAR01');
        $vencida = $this->activo('TBGAR02');
        $vigente = $this->activo('TBGAR03');

        Compra::create(['fk_activo' => $porVencer->id, 'Fecha_Compra' => now()->subMonths(12)->addDays(10), 'Tiempo_garantia' => '12 meses']);
        Compra::create(['fk_activo' => $vencida->id, 'Fecha_Compra' => now()->subYear(), 'Garantia' => now()->subDays(5)->format('Y-m-d')]);
        Compra::create(['fk_activo' => $vigente->id, 'Fecha_Compra' => now(), 'Tiempo_garantia' => '24 meses']);

        $filtros = app(IndicadoresService::class)->normalizarFiltros([]);
        $kpis = app(IndicadoresService::class)->kpis($filtros);

        $this->assertSame(1, $kpis['garantias_por_vencer']);
        $this->assertSame(1, $kpis['garantias_vencidas']);
    }

    public function test_la_pantalla_renderiza_graficos_y_reportes(): void
    {
        $this->activo('TBSRV01');

        $this->actingAs($this->admin)
            ->get(route('indicadores.index'))
            ->assertOk()
            ->assertSee('Reportes y KPIs')
            ->assertSee('Movimientos mensuales')
            ->assertSee('<svg', false)
            ->assertSee('Inventario por categoría')
            ->assertSee('Altas y bajas por mes');
    }

    public function test_exporta_pdf_para_los_siete_reportes(): void
    {
        $this->activo('TBPDF01');

        foreach (array_keys(IndicadoresService::REPORTES) as $reporte) {
            $respuesta = $this->actingAs($this->admin)
                ->get(route('indicadores.exportar', ['reporte' => $reporte]));

            $respuesta->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('content-disposition');
        }
    }

    public function test_exporta_el_resumen_de_kpis_como_csv(): void
    {
        $this->activo('TBCSV01');

        $respuesta = $this->actingAs($this->admin)
            ->get(route('indicadores.exportar', [
                'reporte' => IndicadoresService::KPI_RESUMEN,
                'desde' => '2026-01-01',
                'hasta' => '2026-06-30',
            ]));

        $respuesta->assertOk();

        $contenido = $respuesta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('Indicador;Valor', $contenido);
        $this->assertStringContainsString('"Periodo desde";2026-01-01', $contenido);
        $this->assertStringContainsString('"Equipos registrados";1', $contenido);
        $this->assertStringContainsString('kpi-resumen-', $respuesta->headers->get('content-disposition'));
    }

    public function test_un_reporte_invalido_es_rechazado(): void
    {
        $this->actingAs($this->admin)
            ->get(route('indicadores.index', ['reporte' => 'no-existe']))
            ->assertInvalid(['reporte']);

        $this->actingAs($this->admin)
            ->get(route('indicadores.exportar', ['reporte' => 'no-existe']))
            ->assertInvalid(['reporte']);
    }

    public function test_las_fechas_invalidas_son_rechazadas(): void
    {
        $this->actingAs($this->admin)
            ->get(route('indicadores.index', ['desde' => 'no-es-fecha']))
            ->assertInvalid(['desde']);

        $this->actingAs($this->admin)
            ->get(route('indicadores.index', ['desde' => '2026-06-30', 'hasta' => '2026-01-01']))
            ->assertInvalid(['hasta']);
    }
}
