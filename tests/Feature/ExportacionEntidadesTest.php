<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\HistorialEvento;
use App\Models\Mantenimiento;
use App\Models\ModeloActivo;
use App\Models\Personal;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\ExportacionCompletaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportacionEntidadesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $soporte;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->soporte = User::factory()->create(['role' => 'SoporteTecnico']);
        $this->auditor = User::factory()->create(['role' => 'Auditor']);
    }

    public function test_entidades_permitidas_exportan_un_csv_para_admin(): void
    {
        foreach (array_keys(ExportacionCompletaService::ETIQUETAS) as $entidad) {
            $response = $this->actingAs($this->admin)
                ->get(route('importaciones.exportar', ['entidad' => $entidad]));

            $response->assertOk();
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        }
    }

    public function test_entidad_invalida_es_rechazada(): void
    {
        $this->actingAs($this->admin)
            ->get(route('importaciones.exportar', ['entidad' => 'inexistente']))
            ->assertStatus(422);
    }

    public function test_entidades_admin_solo_son_accesibles_para_admin(): void
    {
        foreach (ExportacionCompletaService::SOLO_ADMIN as $entidad) {
            $this->actingAs($this->soporte)
                ->get(route('importaciones.exportar', ['entidad' => $entidad]))
                ->assertForbidden();
        }
    }

    public function test_auditor_no_puede_exportar_ninguna_entidad(): void
    {
        $this->actingAs($this->auditor)
            ->get(route('importaciones.exportar'))
            ->assertForbidden();
    }

    public function test_exportacion_de_usuarios_no_incluye_password(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('importaciones.exportar', ['entidad' => 'usuarios']))
            ->assertOk();

        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString($this->admin->email, $contenido);
        $this->assertStringNotContainsString('$2y$', $contenido);
        $this->assertStringNotContainsString('password', $contenido);
    }

    public function test_exportacion_de_usuarios_refleja_rol_y_estado(): void
    {
        User::factory()->create([
            'role' => 'Auditor',
            'activo' => false,
            'email' => 'inactivo@prueba.test',
        ]);

        $contenido = $this->actingAs($this->admin)
            ->get(route('importaciones.exportar', ['entidad' => 'usuarios']))
            ->streamedContent();

        $this->assertStringContainsString('Auditor', $contenido);
        $this->assertStringContainsString('inactivo@prueba.test', $contenido);
        $this->assertStringContainsString('No', $contenido);
    }

    public function test_exportacion_de_personal_incluye_inactivos_y_ubicacion(): void
    {
        $ubicacion = Ubicacion::create(['Nombre' => 'Planta Norte', 'Estado' => 'Activo']);
        Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Gómez',
            'Nombre_usuario' => 'agomez',
            'Cargo' => 'Analista',
            'fk_ubicacion' => $ubicacion->id,
            'Estado' => 'Inactivo',
        ]);

        $contenido = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar', ['entidad' => 'personal']))
            ->streamedContent();

        $this->assertStringContainsString('Ana', $contenido);
        $this->assertStringContainsString('agomez', $contenido);
        $this->assertStringContainsString('Planta Norte', $contenido);
        $this->assertStringContainsString('Inactivo', $contenido);
    }

    public function test_exportacion_de_asignaciones_incluye_historial_cerrado(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Disponible', 'Grupo' => 'Disponible', 'Estado' => 1]);
        $persona = Personal::create(['Nombre' => 'Luis', 'Apellido' => 'Ramos', 'Nombre_usuario' => 'lramos', 'Estado' => 'Activo']);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Lenovo T14',
            'Etiqueta_activo' => 'TB-LAP-0100',
            'Categoria' => 'Laptop',
            'Estado' => 'Disponible',
            'fk_estado' => $estado->id,
        ]);

        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->soporte->id,
            'Fecha_asignacion' => '2025-02-01',
            'Fecha_devolucion' => '2025-03-01',
            'Estado' => 'Cerrada',
            'Observaciones' => 'Cerrada tras devolución',
        ]);

        $contenido = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar', ['entidad' => 'asignaciones']))
            ->streamedContent();

        $this->assertStringContainsString('TB-LAP-0100', $contenido);
        $this->assertStringContainsString('Luis Ramos', $contenido);
        $this->assertStringContainsString('Cerrada', $contenido);
    }

    public function test_exportacion_de_mantenimientos_cuenta_evidencias_sin_binarios(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Reparación', 'Grupo' => 'Reparacion', 'Estado' => 1]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Monitor Dell U2422',
            'Etiqueta_activo' => 'TB-MON-0200',
            'Categoria' => 'Monitor',
            'Estado' => 'Reparacion',
            'fk_estado' => $estado->id,
        ]);

        $mantenimiento = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Cambio de pastillas',
            'Fecha_mantenimiento' => '2025-04-10',
            'fk_usuario' => $this->soporte->id,
        ]);
        $mantenimiento->evidencias()->create([
            'Ruta_archivo' => 'evidencias/una.png',
            'Nombre_original' => 'una.png',
            'Tipo_mime' => 'image/png',
            'Tamano_bytes' => 1024,
        ]);

        $contenido = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar', ['entidad' => 'mantenimientos']))
            ->streamedContent();

        $this->assertStringContainsString('TB-MON-0200', $contenido);
        $this->assertStringContainsString('Preventivo', $contenido);
        $this->assertStringContainsString('1', $contenido);
    }

    public function test_exportacion_de_desincorporaciones_incluye_anuladas(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Disponible', 'Grupo' => 'Disponible', 'Estado' => 1]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Impresora HP M404',
            'Etiqueta_activo' => 'TB-IMP-0300',
            'Categoria' => 'Impresora',
            'Estado' => 'Disponible',
            'fk_estado' => $estado->id,
        ]);

        Desincorporacion::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Venta',
            'Destinatario' => 'Cliente X',
            'Monto' => 150.00,
            'Moneda' => 'USD',
            'Motivo' => 'Obsoleta',
            'Fecha' => '2025-05-20',
            'Estado' => 'Anulada',
            'fk_usuario' => $this->soporte->id,
        ]);

        $contenido = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar', ['entidad' => 'desincorporaciones']))
            ->streamedContent();

        $this->assertStringContainsString('TB-IMP-0300', $contenido);
        $this->assertStringContainsString('Anulada', $contenido);
        $this->assertStringContainsString('Cliente X', $contenido);
    }

    public function test_exportacion_de_auditoria_es_solo_admin(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Disponible', 'Grupo' => 'Disponible', 'Estado' => 1]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Teclado Logitech K120',
            'Etiqueta_activo' => 'TB-TEC-0400',
            'Categoria' => 'Accesorio',
            'Estado' => 'Disponible',
            'fk_estado' => $estado->id,
        ]);

        HistorialEvento::create([
            'fk_activo' => $activo->id,
            'Tipo_evento' => 'Creacion',
            'Descripcion' => 'Activo dado de alta',
            'fk_usuario' => $this->admin->id,
            'Fecha' => now(),
        ]);

        // Soporte no puede
        $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar', ['entidad' => 'historial']))
            ->assertForbidden();

        // Admin sí
        $contenido = $this->actingAs($this->admin)
            ->get(route('importaciones.exportar', ['entidad' => 'historial']))
            ->streamedContent();

        $this->assertStringContainsString('TB-TEC-0400', $contenido);
        $this->assertStringContainsString('Creacion', $contenido);
    }

    public function test_exportacion_de_catalogos_vuelca_los_tres_catalogos(): void
    {
        EstadoActivo::create(['Nombre' => 'Asignado', 'Grupo' => 'Asignado', 'Estado' => 1]);
        Ubicacion::create(['Nombre' => 'Sede Central', 'Estado' => 'Activo']);
        ModeloActivo::create([
            'Nombre' => 'PowerEdge R740',
            'Fabricante' => 'Dell',
            'Categoria' => 'Servidor',
            'Estado' => 'Activo',
        ]);

        $contenido = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar', ['entidad' => 'catalogos']))
            ->streamedContent();

        $this->assertStringContainsString('Estados', $contenido);
        $this->assertStringContainsString('Asignado', $contenido);
        $this->assertStringContainsString('Ubicaciones', $contenido);
        $this->assertStringContainsString('Sede Central', $contenido);
        $this->assertStringContainsString('Modelos', $contenido);
        $this->assertStringContainsString('PowerEdge R740', $contenido);
    }

    public function test_exportacion_de_activos_por_defecto_sigue_siendo_la_original(): void
    {
        // Sin parámetro entidad debe caer en `activos` (compatibilidad)
        $estado = EstadoActivo::create(['Nombre' => 'Disponible', 'Grupo' => 'Disponible', 'Estado' => 1]);
        Activo::create([
            'Nombre_de_activo' => 'Switch Cisco C9200',
            'Etiqueta_activo' => 'TB-SWI-0500',
            'Categoria' => 'Switch',
            'Estado' => 'Disponible',
            'fk_estado' => $estado->id,
        ]);

        $respuesta = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar'))
            ->assertOk();

        $contenido = $respuesta->streamedContent();

        // Cabeceras canónicas originales intactas
        $this->assertStringContainsString('Nombre de Activo', $contenido);
        $this->assertStringContainsString('TB-SWI-0500', $contenido);
    }
}
