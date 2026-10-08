<?php

namespace Tests\Feature;

use App\Models\ImportacionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportacionTest extends TestCase
{
    use RefreshDatabase;

    private User $soporte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->soporte = User::factory()->create(['role' => 'SoporteTecnico']);
    }

    /**
     * Construye un CSV con la cabecera real de Snipec IT; cada fila se da
     * como asociación NombreDeColumna => valor (el resto queda vacío).
     */
    private function csvFilas(array $filas): string
    {
        $columnas = ['Compaña', 'Nombre de Activo', 'Etiqueta de Activo', 'Modelo', 'Modelo n.',
            'Categoría', 'Fabricante', 'Serial', 'Comprado', 'Costo', 'Número de Orden', 'Proveedor',
            'Localización', 'Ubicación Predeterminada', 'Asignado', 'Tipo', 'Username', 'Responsable',
            'Departamento', 'Estado', 'Notas'];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $columnas, ',', '"', '\\');

        foreach ($filas as $sobrescrituras) {
            $fila = array_fill(0, count($columnas), '');
            $fila[0] = 'Tubrica';

            foreach ($sobrescrituras as $nombre => $valor) {
                $indice = array_search($nombre, $columnas, true);

                if ($indice !== false) {
                    $fila[$indice] = $valor;
                }
            }

            fputcsv($stream, $fila, ',', '"', '\\');
        }

        rewind($stream);
        $contenido = stream_get_contents($stream);
        fclose($stream);

        return $contenido;
    }

    /**
     * CSV de ejemplo con una fila válida (user), una fila location y una fila inválida.
     */
    private function csvDePrueba(): string
    {
        return $this->csvFilas([
            [
                'Nombre de Activo' => 'Laptop Dell 5520',
                'Etiqueta de Activo' => 'TBIMP0001',
                'Modelo' => 'Latitude',
                'Categoría' => 'Laptops',
                'Fabricante' => 'Dell',
                'Serial' => 'DL001',
                'Localización' => 'Oficina Sistemas',
                'Asignado' => 'Glenire Rodriguez',
                'Tipo' => 'user',
                'Username' => 'Grodriguez',
                'Estado' => 'Asignado (deployed)',
            ],
            [
                'Nombre de Activo' => 'Monitor LG',
                'Etiqueta de Activo' => 'TBIMP0002',
                'Modelo' => '27MK430',
                'Categoría' => 'Monitores',
                'Fabricante' => 'LG',
                'Serial' => 'N/A',
                'Localización' => 'Oficina Sistemas',
                'Asignado' => 'TUBRICA > Oficina',
                'Tipo' => 'location',
                'Estado' => 'Disponible (deployable)',
            ],
            [
                'Etiqueta de Activo' => 'TBIMP0003',
                'Estado' => 'Disponible (deployable)',
            ],
        ]);
    }

    public function test_dry_run_no_persiste_activos(): void
    {
        Storage::fake('local');

        $respuesta = $this->actingAs($this->soporte)->post('/importaciones', [
            'archivo' => UploadedFile::fake()->createWithContent('reporte.csv', $this->csvDePrueba()),
            'modo' => 'dryrun',
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ACTIVOS', 0);
        $this->assertDatabaseCount('PERSONAL', 0);
        $this->assertDatabaseHas('IMPORTACIONES_LOG', ['Modo' => 'DryRun', 'Insertadas' => 2, 'Errores' => 1]);

        $logId = ImportacionLog::latest('id')->value('id');
        $this->assertDatabaseHas('IMPORTACIONES_DETALLE', ['fk_importacion' => $logId, 'Accion' => 'Error']);
        $this->assertNotNull(session('importacion_pendiente'));
    }

    public function test_commit_importa_y_confirmar_es_idempotente(): void
    {
        Storage::fake('local');

        $csv = UploadedFile::fake()->createWithContent('reporte.csv', $this->csvDePrueba());

        $this->actingAs($this->soporte)->post('/importaciones', [
            'archivo' => $csv,
            'modo' => 'dryrun',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->soporte)->post('/importaciones/confirmar')
            ->assertSessionHasNoErrors();

        // Resultado de la importación real
        $this->assertDatabaseCount('ACTIVOS', 2);
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBIMP0001', 'Estado' => 'Asignado']);
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBIMP0002', 'Estado' => 'Disponible']);
        $this->assertDatabaseHas('PERSONAL', ['Nombre_usuario' => 'Grodriguez', 'Nombre' => 'Glenire']);
        $this->assertDatabaseHas('ASIGNACIONES', ['Estado' => 'Activa', 'Observaciones' => 'Importado desde Snipec IT']);
        $this->assertDatabaseHas('ESTADOS_ACTIVO', ['Nombre' => 'Asignado (deployed)', 'Grupo' => 'Asignado']);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', ['Tipo_evento' => 'Importacion']);
        $this->assertNull(session('importacion_pendiente'));

        // Re-importar el mismo archivo: idempotente (SinCambios)
        $this->actingAs($this->soporte)->post('/importaciones', [
            'archivo' => UploadedFile::fake()->createWithContent('reporte.csv', $this->csvDePrueba()),
            'modo' => 'commit',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ACTIVOS', 2);
        $this->assertDatabaseCount('ASIGNACIONES', 1);
        $this->assertDatabaseCount('PERSONAL', 1);

        $segundoLog = ImportacionLog::orderByDesc('id')->first();
        $this->assertSame('Commit', $segundoLog->Modo);
        $this->assertSame(0, $segundoLog->Insertadas);
        $this->assertSame(0, $segundoLog->Actualizadas);
        $this->assertSame(1, $segundoLog->Errores);
    }

    public function test_duplicados_dentro_del_archivo_no_duplican_registros(): void
    {
        Storage::fake('local');

        $csv = $this->csvFilas([
            ['Nombre de Activo' => 'Notebook HP', 'Etiqueta de Activo' => 'TBDUP0001', 'Serial' => 'SERIAL123', 'Estado' => 'Asignado (deployed)'],
            ['Nombre de Activo' => 'Notebook HP variante', 'Etiqueta de Activo' => 'TBDUP0001', 'Serial' => 'SERIAL999', 'Estado' => 'Disponible (deployable)'],
            ['Nombre de Activo' => 'Impresora Epson', 'Etiqueta de Activo' => 'TBDUP0002', 'Serial' => 'SERIAL123', 'Estado' => 'Disponible (deployable)'],
        ]);

        $this->actingAs($this->soporte)->post('/importaciones', [
            'archivo' => UploadedFile::fake()->createWithContent('dupes.csv', $csv),
            'modo' => 'commit',
        ])->assertSessionHasNoErrors();

        // 3 filas -> 2 activos: la etiqueta repetida no se duplica y el serial repetido se deja vacío
        $this->assertDatabaseCount('ACTIVOS', 2);
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBDUP0001', 'Serial' => 'SERIAL123', 'Nombre_de_activo' => 'Notebook HP']);
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBDUP0002', 'Serial' => null]);

        $log = ImportacionLog::latest('id')->first();
        $this->assertSame(2, $log->Insertadas);
        $this->assertSame(0, $log->Errores);
        $this->assertDatabaseHas('IMPORTACIONES_DETALLE', [
            'fk_importacion' => $log->id,
            'Etiqueta' => 'TBDUP0001',
            'Accion' => 'SinCambios',
        ]);
    }

    public function test_csv_con_columnas_invalidas_es_rechazado(): void
    {
        $respuesta = $this->actingAs($this->soporte)->post('/importaciones', [
            'archivo' => UploadedFile::fake()->createWithContent('malo.csv', "columna1,columna2\n1,2\n"),
            'modo' => 'dryrun',
        ]);

        $respuesta->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('IMPORTACIONES_LOG', 0);
    }

    public function test_archivo_no_csv_es_rechazado(): void
    {
        $this->actingAs($this->soporte)->post('/importaciones', [
            'archivo' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
            'modo' => 'dryrun',
        ])->assertSessionHasErrors('archivo');
    }

    public function test_auditor_no_puede_importar(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)
            ->get('/importaciones')
            ->assertForbidden();

        $this->actingAs($auditor)
            ->post('/importaciones', [
                'archivo' => UploadedFile::fake()->createWithContent('x.csv', $this->csvDePrueba()),
                'modo' => 'dryrun',
            ])
            ->assertForbidden();
    }
}
