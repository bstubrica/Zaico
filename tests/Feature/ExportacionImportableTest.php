<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Compra;
use App\Models\EstadoActivo;
use App\Models\Personal;
use App\Models\TarjetaRed;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\ExportacionCompletaService;
use App\Services\ImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportacionImportableTest extends TestCase
{
    use RefreshDatabase;

    private User $soporte;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->soporte = User::factory()->create(['role' => 'SoporteTecnico']);
        $this->auditor = User::factory()->create(['role' => 'Auditor']);
    }

    public function test_solo_admin_y_soporte_pueden_exportar_la_data_completa(): void
    {
        $this->actingAs($this->auditor)
            ->get(route('importaciones.exportar'))
            ->assertForbidden();

        $response = $this->actingAs($this->soporte)
            ->get(route('importaciones.exportar'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_la_exportacion_completa_es_reinterpretable_por_el_importador(): void
    {
        // 1. Crear entorno y activos con relaciones completas
        $estado = EstadoActivo::create([
            'Nombre' => 'Asignado (Producción)',
            'Grupo' => 'Asignado',
            'Deployed' => true,
            'Deployable' => true,
            'Estado' => 1,
        ]);
        $ubicacion = Ubicacion::create(['Nombre' => 'Planta Principal', 'Estado' => 'Activo']);
        $persona = Personal::create([
            'Nombre' => 'Carlos',
            'Apellido' => 'Pérez',
            'Nombre_usuario' => 'cperez',
            'Estado' => 'Activo',
        ]);

        $activo = Activo::create([
            'Nombre_de_activo' => 'Servidor Dell PowerEdge R740 TB0001',
            'Etiqueta_activo' => 'TB-SRV-0001',
            'Serial' => 'SRV-DEL-740',
            'Categoria' => 'Servidores',
            'Fabricante' => 'Dell',
            'Modelo' => 'PowerEdge R740',
            'Ubicacion' => 'Data Center',
            'Estado' => 'Asignado',
            'fk_estado' => $estado->id,
            'fk_ubicacion' => $ubicacion->id,
            'IP' => '10.0.0.50',
            'Direccion_MAC' => 'AA:BB:CC:DD:EE:01',
            'Sistema_Operativo' => 'Ubuntu 24.04 LTS',
            'Memoria_RAM' => '64GB',
            'Capacidad_Disco' => '2TB SSD',
            'Estatus_Servidor' => 'Encendido',
            'Observaciones' => 'Servidor de base de datos principal',
        ]);

        // Compra
        Compra::create([
            'fk_activo' => $activo->id,
            'Fecha_Compra' => '2025-01-15',
            'Costo_compra' => 4500.00,
            'Numero_Requisicion' => 'REQ-2025-001',
            'Proveedor' => 'Dell Computación',
            'Numero_factura' => 'FAC-998822',
            'Tiempo_garantia' => 36,
        ]);

        // Asignación
        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->soporte->id,
            'Fecha_asignacion' => '2025-01-20',
            'Estado' => 'Activa',
            'Observaciones' => 'Asignado a Carlos Pérez',
        ]);

        // Tarjeta de red
        TarjetaRed::create([
            'fk_activo' => $activo->id,
            'Numero' => 1,
            'Nombre' => 'eth0',
            'Direccion_MAC' => 'AA:BB:CC:DD:EE:01',
            'IP' => '10.0.0.50',
            'Switch' => 'SW-CORE-01',
            'Puerto' => 'G1/0/1',
        ]);

        // 2. Realizar la exportación
        $exportador = app(ExportacionCompletaService::class);
        $fila = $exportador->mapearFila($activo->fresh([
            'compra',
            'asignacionActiva.personal.ubicacion',
            'ubicacionCatalogo',
            'modeloCatalogo',
            'estadoCatalogo',
            'tarjetasRed.switchCatalogo',
            'desincorporacion',
        ]));

        $cabeceras = ExportacionCompletaService::CABECERAS;
        $this->assertCount(count($cabeceras), $fila);

        // Verificamos mapeo
        $this->assertEquals('Servidor Dell PowerEdge R740 TB0001', $fila[1]);
        $this->assertEquals('TB-SRV-0001', $fila[2]);
        $this->assertEquals('PowerEdge R740', $fila[3]);
        $this->assertEquals('Servidores', $fila[5]);
        $this->assertEquals('Dell', $fila[6]);
        $this->assertEquals('SRV-DEL-740', $fila[7]);
        $this->assertEquals('2025-01-15', $fila[8]);
        $this->assertEquals('4500.00', $fila[9]);
        $this->assertEquals('REQ-2025-001', $fila[10]);
        $this->assertEquals('Dell Computación', $fila[11]);
        $this->assertEquals('FAC-998822', $fila[12]);
        $this->assertEquals('36', $fila[13]);
        $this->assertEquals('cperez', $fila[18]);
        $this->assertEquals('AA:BB:CC:DD:EE:01', $fila[23]);
        $this->assertEquals('10.0.0.50', $fila[24]);
        $this->assertEquals('Ubuntu 24.04 LTS', $fila[25]);

        // 3. Crear archivo CSV en memoria con el contenido exportado
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $cabeceras, ',', '"', '\\');
        fputcsv($stream, $fila, ',', '"', '\\');
        rewind($stream);
        $csvContenido = stream_get_contents($stream);
        fclose($stream);

        // 4. Probar importación round-trip en ImportacionService
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContenido);

        $importador = app(ImportacionService::class);
        $log = $importador->importarCsv($tempFile, 'exportacion_test.csv', true, $this->soporte->id);

        @unlink($tempFile);

        $this->assertEquals(1, $log->Total_filas);
        $this->assertEquals(0, $log->Errores);
        // Debe reconocer que el activo ya existe y procesarlo sin errores
        $this->assertContains($log->detalles->first()->Accion, ['SinCambios', 'Actualizado']);
    }
}
