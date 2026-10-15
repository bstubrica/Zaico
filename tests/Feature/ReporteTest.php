<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    public function test_exportar_activos_genera_csv_con_cabeceras_y_datos(): void
    {
        Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBREP0001',
            'Serial' => 'SN-123',
            'Categoria' => 'Laptop',
            'Estado' => 'Disponible',
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.activos'));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=activos-', $respuesta->headers->get('content-disposition'));

        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('Etiqueta;Nombre;Serial', $contenido);
        $this->assertStringContainsString('TBREP0001', $contenido);
        $this->assertStringContainsString('Laptop Dell', $contenido);
        $this->assertStringContainsString('SN-123', $contenido);
    }

    public function test_neutraliza_inyeccion_de_formulas_en_csv(): void
    {
        Activo::create([
            'Nombre_de_activo' => '=cmd|\' /c calc\'!A0',
            'Etiqueta_activo' => 'TBINY0001',
            'Estado' => 'Disponible',
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.activos'));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString("'=cmd", $contenido);
        $this->assertStringNotContainsString(';=cmd', $contenido);
    }

    public function test_exportar_personal_genera_csv(): void
    {
        Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Pérez',
            'Nombre_usuario' => 'aperez',
            'Cargo' => 'Analista',
            'Estado' => 'Activo',
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.personal'));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('Nombre;Apellido;Usuario', $contenido);
        $this->assertStringContainsString('Ana;Pérez;aperez', $contenido);
    }

    public function test_exportar_historial_genera_csv(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Monitor LG',
            'Etiqueta_activo' => 'TBREP0002',
            'Estado' => 'Asignado',
        ]);
        $persona = Personal::create([
            'Nombre' => 'Luis',
            'Apellido' => 'Gómez',
            'Nombre_usuario' => 'lgomez',
            'Estado' => 'Activo',
        ]);
        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->admin->id,
            'Fecha_asignacion' => '2026-10-01',
            'Estado' => 'Activa',
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.historial'));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('Etiqueta;Equipo;Persona', $contenido);
        $this->assertStringContainsString('TBREP0002', $contenido);
        $this->assertStringContainsString('Monitor LG', $contenido);
        $this->assertStringContainsString('Luis Gómez', $contenido);
    }

    public function test_exportar_mantenimientos_genera_csv(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Impresora HP',
            'Etiqueta_activo' => 'TBREP0003',
            'Estado' => 'Disponible',
        ]);
        Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Limpieza general',
            'Fecha_mantenimiento' => '2026-10-02',
            'fk_usuario' => $this->admin->id,
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.mantenimientos'));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('Fecha;Etiqueta;Equipo;Tipo', $contenido);
        $this->assertStringContainsString('TBREP0003', $contenido);
        $this->assertStringContainsString('Impresora HP', $contenido);
        $this->assertStringContainsString('Impresora HP";Preventivo;"Limpieza general', $contenido);
    }

    public function test_auditor_puede_exportar_reportes(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get(route('reportes.activos'))->assertOk();
    }

    public function test_exportacion_requiere_autenticacion(): void
    {
        $this->get(route('reportes.activos'))->assertRedirect(route('login'));
    }

    public function test_exportar_desincorporados_genera_csv(): void
    {
        $estado = EstadoActivo::create([
            'Nombre' => 'Desincorporado (undeployable)',
            'Grupo' => 'Desincorporado',
            'Deployed' => true,
            'Deployable' => false,
            'Estado' => 1,
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBDES001',
            'Estado' => 'Desincorporado',
            'fk_estado' => $estado->id,
        ]);
        Desincorporacion::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Venta',
            'Destinatario' => 'Proveedor ACME',
            'Monto' => 120.50,
            'Moneda' => 'USD',
            'Motivo' => 'Obsolescencia',
            'Fecha' => '2026-10-05',
            'Estado' => 'Vigente',
            'fk_usuario' => $this->admin->id,
        ]);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.desincorporados'));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('"Tipo de baja";Destinatario', $contenido);
        $this->assertStringContainsString('TBDES001', $contenido);
        $this->assertStringContainsString('Venta', $contenido);
        $this->assertStringContainsString('Proveedor ACME', $contenido);
    }
}
