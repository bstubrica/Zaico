<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\EvidenciaMantenimiento;
use App\Models\HistorialEvento;
use App\Models\ImportacionDetalle;
use App\Models\ImportacionLog;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use App\Services\ActivoService;
use App\Services\HistorialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EliminacionRegistrosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    private function estado(string $nombre, string $grupo): EstadoActivo
    {
        return EstadoActivo::firstOrCreate(
            ['Nombre' => $nombre],
            [
                'Grupo' => $grupo,
                'Deployed' => $grupo === 'Desincorporado',
                'Deployable' => false,
                'Estado' => 1,
            ]
        );
    }

    private function activo(string $etiqueta, ?EstadoActivo $estado = null): Activo
    {
        $estado ??= $this->estado('Disponible (deployable)', 'Disponible');

        return Activo::create([
            'Nombre_de_activo' => 'Equipo '.$etiqueta,
            'Etiqueta_activo' => $etiqueta,
            'Estado' => $estado->Grupo,
            'fk_estado' => $estado->id,
        ]);
    }

    private function persona(string $usuario, string $estado = 'Activo'): Personal
    {
        return Personal::create([
            'Nombre' => 'Nombre',
            'Apellido' => $usuario,
            'Nombre_usuario' => $usuario,
            'Cargo' => 'Analista',
            'Estado' => $estado,
        ]);
    }

    private function asignacionActiva(Activo $activo, Personal $personal): Asignacion
    {
        return Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $personal->id,
            'fk_usuario' => $this->admin->id,
            'Fecha_asignacion' => now()->toDateString(),
            'Estado' => 'Activa',
        ]);
    }

    private function logImportacion(): ImportacionLog
    {
        $log = ImportacionLog::create([
            'Nombre_archivo' => 'reporte.csv',
            'fk_usuario' => $this->admin->id,
            'Total_filas' => 1,
            'Insertadas' => 1,
            'Actualizadas' => 0,
            'Errores' => 0,
            'Modo' => 'Commit',
            'Estado' => 'Completado',
        ]);

        ImportacionDetalle::create([
            'fk_importacion' => $log->id,
            'Fila_numero' => 1,
            'Etiqueta' => 'TBAUT001',
            'Accion' => 'Insertado',
        ]);

        return $log;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosBaja(): array
    {
        return [
            'tipo' => 'Desecho',
            'destinatario' => 'Chatarra municipal',
            'motivo' => 'Obsolescencia',
            'fecha' => now()->toDateString(),
        ];
    }

    public function test_admin_puede_desincorporar_un_activo(): void
    {
        $desincorporado = $this->estado('Desincorporado (undeployable)', 'Desincorporado');
        $activo = $this->activo('TBDES001');

        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar', $activo), [
                'tipo' => 'Venta',
                'destinatario' => 'Proveedor ACME',
                'monto' => 120.50,
                'motivo' => 'Obsolescencia',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $activo->refresh();
        $this->assertSame('Desincorporado', $activo->Estado);
        $this->assertSame($desincorporado->id, $activo->fk_estado);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $activo->id,
            'Tipo_evento' => 'Desincorporacion',
        ]);
        $this->assertDatabaseHas('DESINCORPORACIONES', [
            'fk_activo' => $activo->id,
            'Tipo' => 'Venta',
            'Estado' => 'Vigente',
        ]);
    }

    public function test_no_se_desincorpora_un_activo_con_asignacion_activa(): void
    {
        $this->estado('Desincorporado (undeployable)', 'Desincorporado');
        $activo = $this->activo('TBDES002');
        $this->asignacionActiva($activo, $this->persona('jperez'));

        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar', $activo), [
                'tipo' => 'Desecho',
                'destinatario' => 'Chatarra',
                'motivo' => 'x',
                'fecha' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('desincorporar');

        $this->assertSame('Disponible', $activo->fresh()->Estado);
    }

    public function test_desincorporacion_masiva_omite_con_asignacion_y_ya_desincorporados(): void
    {
        $desincorporado = $this->estado('Desincorporado (undeployable)', 'Desincorporado');
        $a1 = $this->activo('TBDES003');
        $a2 = $this->activo('TBDES004');
        $ocupado = $this->activo('TBDES005');
        $this->asignacionActiva($ocupado, $this->persona('jperez'));
        $yaDesincorporado = $this->activo('TBDES006', $desincorporado);

        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar-masivo'), [
                'ids' => [$a1->id, $a2->id, $ocupado->id, $yaDesincorporado->id],
                'motivo' => 'Obsolescencia',
            ] + $this->datosBaja())
            ->assertSessionHasNoErrors();

        $this->assertSame('Desincorporado', $a1->fresh()->Estado);
        $this->assertSame('Desincorporado', $a2->fresh()->Estado);
        $this->assertSame('Disponible', $ocupado->fresh()->Estado);
        $this->assertSame('Desincorporado', $yaDesincorporado->fresh()->Estado);
        $this->assertDatabaseCount('ACTIVOS', 4);
    }

    public function test_desincorporacion_masiva_requiere_ids_y_motivo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar-masivo'), ['ids' => [], 'motivo' => ''])
            ->assertSessionHasErrors(['ids', 'motivo']);
    }

    public function test_desincorporacion_masiva_selecciona_todos_los_filtrados(): void
    {
        $this->estado('Desincorporado (undeployable)', 'Desincorporado');
        $a1 = $this->activo('TBTOT001');
        $a2 = $this->activo('TBTOT002');
        $ocupado = $this->activo('TBTOT003');
        $this->asignacionActiva($ocupado, $this->persona('tbusuario'));
        $ajeno = $this->activo('TBOTRO01');

        $this->actingAs($this->admin)
            ->post(route('activos.desincorporar-masivo'), [
                'todos' => 1,
                'termino' => 'TBTOT',
                'motivo' => 'Obsolescencia',
            ] + $this->datosBaja())
            ->assertSessionHasNoErrors();

        $this->assertSame('Desincorporado', $a1->fresh()->Estado);
        $this->assertSame('Desincorporado', $a2->fresh()->Estado);
        $this->assertSame('Disponible', $ocupado->fresh()->Estado);
        $this->assertSame('Disponible', $ajeno->fresh()->Estado);
    }

    public function test_desincorporacion_masiva_es_atomica_ante_un_error(): void
    {
        $this->estado('Desincorporado (undeployable)', 'Desincorporado');
        $a1 = $this->activo('TBATM001');
        $a2 = $this->activo('TBATM002');

        $historial = new class extends HistorialService
        {
            public int $llamadas = 0;

            public function registrar(
                int $activoId,
                string $tipo,
                string $descripcion,
                ?string $anterior = null,
                ?string $nuevo = null,
                ?int $usuarioId = null,
            ): HistorialEvento {
                $this->llamadas++;

                if ($this->llamadas > 1) {
                    throw new RuntimeException('Fallo simulado de auditoría.');
                }

                return parent::registrar($activoId, $tipo, $descripcion, $anterior, $nuevo, $usuarioId);
            }
        };

        $service = new ActivoService($historial);

        try {
            $service->desincorporarLote([$a1->id, $a2->id], $this->datosBaja(), $this->admin->id);
            $this->fail('Se esperaba una RuntimeException que revierta todo el lote.');
        } catch (RuntimeException) {
            // Esperado: la transacción debe revertir el lote completo.
        }

        $this->assertSame('Disponible', $a1->fresh()->Estado);
        $this->assertSame('Disponible', $a2->fresh()->Estado);
        $this->assertDatabaseCount('HISTORIAL_EVENTOS', 0);
    }

    public function test_admin_puede_dar_de_baja_una_persona(): void
    {
        $persona = $this->persona('jperez');

        $this->actingAs($this->admin)
            ->delete(route('personal.destroy', $persona))
            ->assertRedirect();

        $this->assertSame('Inactivo', $persona->fresh()->Estado);
    }

    public function test_baja_masiva_omite_personas_con_equipos_asignados(): void
    {
        $p1 = $this->persona('jperez');
        $p2 = $this->persona('mgomez');
        $this->asignacionActiva($this->activo('TBDES007'), $p2);

        $this->actingAs($this->admin)
            ->post(route('personal.baja-masiva'), ['ids' => [$p1->id, $p2->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame('Inactivo', $p1->fresh()->Estado);
        $this->assertSame('Activo', $p2->fresh()->Estado);
        $this->assertDatabaseCount('PERSONAL', 2);
    }

    public function test_baja_masiva_selecciona_todos_los_filtrados(): void
    {
        $p1 = $this->persona('tbtot1');
        $p2 = $this->persona('tbtot2');
        $conEquipo = $this->persona('tbtot3');
        $this->asignacionActiva($this->activo('TBBTOT01'), $conEquipo);
        $ajena = $this->persona('otra01');

        $this->actingAs($this->admin)
            ->post(route('personal.baja-masiva'), [
                'todos' => 1,
                'termino' => 'tbtot',
                'estado' => 'Activo',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Inactivo', $p1->fresh()->Estado);
        $this->assertSame('Inactivo', $p2->fresh()->Estado);
        $this->assertSame('Activo', $conEquipo->fresh()->Estado);
        $this->assertSame('Activo', $ajena->fresh()->Estado);
    }

    public function test_se_elimina_una_importacion_y_su_detalle(): void
    {
        $log = $this->logImportacion();

        $this->actingAs($this->admin)
            ->delete(route('importaciones.destroy', $log))
            ->assertRedirect();

        $this->assertDatabaseCount('IMPORTACIONES_LOG', 0);
        $this->assertDatabaseCount('IMPORTACIONES_DETALLE', 0);
    }

    public function test_se_eliminan_varias_importaciones(): void
    {
        $primero = $this->logImportacion();
        $segundo = $this->logImportacion();

        $this->actingAs($this->admin)
            ->post(route('importaciones.eliminar-masivo'), ['ids' => [$primero->id, $segundo->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('IMPORTACIONES_LOG', 0);
        $this->assertDatabaseCount('IMPORTACIONES_DETALLE', 0);
    }

    public function test_se_eliminan_todas_las_importaciones_seleccionando_todos(): void
    {
        $this->logImportacion();
        $this->logImportacion();

        $this->actingAs($this->admin)
            ->post(route('importaciones.eliminar-masivo'), ['todos' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('IMPORTACIONES_LOG', 0);
        $this->assertDatabaseCount('IMPORTACIONES_DETALLE', 0);
    }

    public function test_se_elimina_mantenimiento_sus_evidencias_y_archivos(): void
    {
        Storage::fake('local');

        $activo = $this->activo('TBDES008');
        $mantenimiento = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Limpieza',
            'Fecha_mantenimiento' => now()->toDateString(),
            'fk_usuario' => $this->admin->id,
        ]);

        $ruta = Storage::disk('local')->putFile(
            'uploads/mantenimientos',
            UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf')
        );

        EvidenciaMantenimiento::create([
            'fk_mantenimiento' => $mantenimiento->id,
            'Ruta_archivo' => $ruta,
            'Nombre_original' => 'evidencia.pdf',
            'Tipo_mime' => 'application/pdf',
            'Tamano_bytes' => 100,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('mantenimientos.destroy', $mantenimiento))
            ->assertRedirect();

        $this->assertDatabaseCount('MANTENIMIENTOS', 0);
        $this->assertDatabaseCount('EVIDENCIAS_MANTENIMIENTO', 0);
        $this->assertFalse(Storage::disk('local')->exists($ruta));
    }

    public function test_se_eliminan_varios_mantenimientos(): void
    {
        Storage::fake('local');

        $activo = $this->activo('TBDES009');
        $primero = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'A',
            'Fecha_mantenimiento' => now()->toDateString(),
            'fk_usuario' => $this->admin->id,
        ]);
        $segundo = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Correctivo',
            'Descripcion' => 'B',
            'Fecha_mantenimiento' => now()->toDateString(),
            'fk_usuario' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('mantenimientos.eliminar-masivo'), ['ids' => [$primero->id, $segundo->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('MANTENIMIENTOS', 0);
    }

    public function test_se_eliminan_mantenimientos_con_todos_los_filtrados(): void
    {
        Storage::fake('local');

        $activo = $this->activo('TBMTO001');
        $otro = $this->activo('TBMTO002');

        foreach ([$activo, $activo, $otro] as $indice => $equipo) {
            Mantenimiento::create([
                'fk_activo' => $equipo->id,
                'Tipo' => 'Preventivo',
                'Descripcion' => 'M'.$indice,
                'Fecha_mantenimiento' => now()->toDateString(),
                'fk_usuario' => $this->admin->id,
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('mantenimientos.eliminar-masivo'), ['todos' => 1, 'activo' => 'TBMTO001'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('MANTENIMIENTOS', 1);
    }

    public function test_auditor_no_puede_desincorporar_ni_borrar(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);
        $activo = $this->activo('TBAUT001');
        $log = $this->logImportacion();
        $mantenimiento = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'x',
            'Fecha_mantenimiento' => now()->toDateString(),
            'fk_usuario' => $this->admin->id,
        ]);
        $persona = $this->persona('jperez');

        $this->actingAs($auditor)
            ->post(route('activos.desincorporar', $activo), ['motivo' => 'x'])
            ->assertForbidden();
        $this->actingAs($auditor)
            ->post(route('activos.desincorporar-masivo'), ['ids' => [$activo->id], 'motivo' => 'x'])
            ->assertForbidden();
        $this->actingAs($auditor)
            ->post(route('personal.baja-masiva'), ['ids' => [$persona->id]])
            ->assertForbidden();
        $this->actingAs($auditor)
            ->delete(route('importaciones.destroy', $log))
            ->assertForbidden();
        $this->actingAs($auditor)
            ->delete(route('mantenimientos.destroy', $mantenimiento))
            ->assertForbidden();
    }
}
