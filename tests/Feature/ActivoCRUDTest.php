<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\EvidenciaMantenimiento;
use App\Models\HistorialEvento;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_acepta_una_direccion_mac_valida(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop con MAC',
            'Etiqueta_activo' => 'TBMAC0001',
            'Estado' => 'Disponible',
            'Direccion_MAC' => '00:11:22:33:44:55',
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBMAC0001', 'Direccion_MAC' => '00:11:22:33:44:55']);
    }

    public function test_rechaza_una_direccion_mac_invalida(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop MAC mala',
            'Etiqueta_activo' => 'TBMAC0002',
            'Estado' => 'Disponible',
            'Direccion_MAC' => 'no-es-una-mac',
        ]);

        $respuesta->assertSessionHasErrors('Direccion_MAC');
    }

    public function test_rechaza_un_grupo_de_estado_inexistente(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Equipo grupo invalido',
            'Etiqueta_activo' => 'TBGRP0001',
            'Estado' => 'Inventado',
        ]);

        $respuesta->assertSessionHasErrors('Estado');
        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBGRP0001']);
    }

    public function test_rechaza_detalle_de_estado_que_no_coincide_con_el_grupo(): void
    {
        $reparacion = EstadoActivo::where('Grupo', 'Reparacion')->firstOrFail();

        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Equipo mismatch',
            'Etiqueta_activo' => 'TBGRP0002',
            'Estado' => 'Disponible',
            'fk_estado' => $reparacion->id,
        ]);

        $respuesta->assertSessionHasErrors('fk_estado');
    }

    public function test_acepta_detalle_de_estado_coherente(): void
    {
        $disponible = EstadoActivo::where('Grupo', 'Disponible')->firstOrFail();

        $respuesta = $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Equipo coherente',
            'Etiqueta_activo' => 'TBGRP0003',
            'Estado' => 'Disponible',
            'fk_estado' => $disponible->id,
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBGRP0003', 'fk_estado' => $disponible->id]);
    }

    public function test_actualiza_activo_cambiando_grupo_y_detalle_coherentes(): void
    {
        $disponible = EstadoActivo::where('Grupo', 'Disponible')->firstOrFail();
        $reparacion = EstadoActivo::where('Grupo', 'Reparacion')->firstOrFail();

        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo a editar',
            'Etiqueta_activo' => 'TBEDT0001',
            'Estado' => 'Disponible',
            'fk_estado' => $disponible->id,
        ]);

        $respuesta = $this->actingAs($this->admin)->put("/activos/{$activo->id}", [
            'Nombre_de_activo' => 'Equipo editado',
            'Etiqueta_activo' => 'TBEDT0001',
            'Estado' => 'Reparacion',
            'fk_estado' => $reparacion->id,
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'Estado' => 'Reparacion', 'fk_estado' => $reparacion->id]);
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

    public function test_el_listado_incluye_la_vista_de_tarjetas(): void
    {
        Activo::create([
            'Nombre_de_activo' => 'Laptop Tarjeta',
            'Etiqueta_activo' => 'TBCARD0001',
            'Categoria' => 'Laptop',
            'Ubicacion' => 'Oficina Central',
            'Estado' => 'Disponible',
        ]);

        $respuesta = $this->actingAs($this->admin)->get('/activos');

        $respuesta->assertOk();
        $respuesta->assertSee('Forma de visualización');
        $respuesta->assertSee('Tarjetas');
        $respuesta->assertSee('Ver ficha');
        $respuesta->assertSee('Laptop Tarjeta');
        $respuesta->assertSee('TBCARD0001');
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

    public function test_no_se_puede_asignar_activo_desincorporado_sin_catalogo(): void
    {
        $persona = Personal::create([
            'Nombre' => 'Rosa',
            'Apellido' => 'Diaz',
            'Nombre_usuario' => 'rdiaz',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo de baja',
            'Etiqueta_activo' => 'TBBAJA0001',
            'Estado' => 'Desincorporado',
        ]);

        $this->actingAs($this->admin)->post("/activos/{$activo->id}/asignacion", [
            'fk_Personal' => $persona->id,
            'Fecha_asignacion' => '2026-10-08',
        ])->assertSessionHasErrors('asignacion');

        $this->assertDatabaseMissing('ASIGNACIONES', ['fk_Activo' => $activo->id]);
    }

    public function test_la_bd_impide_dos_asignaciones_activas(): void
    {
        $persona = Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Lopez',
            'Nombre_usuario' => 'alopez',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop HP',
            'Etiqueta_activo' => 'TBDUPASG01',
            'Estado' => 'Disponible',
        ]);

        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->admin->id,
            'Fecha_asignacion' => '2026-10-08',
            'Estado' => 'Activa',
        ]);

        $this->expectException(QueryException::class);

        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->admin->id,
            'Fecha_asignacion' => '2026-10-09',
            'Estado' => 'Activa',
        ]);
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

    public function test_eventos_se_limita_a_200_registros(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Notebook Lenovo',
            'Etiqueta_activo' => 'TBEVTCAP01',
            'Estado' => 'Disponible',
        ]);

        $filas = [];
        for ($i = 0; $i < 205; $i++) {
            $filas[] = [
                'fk_activo' => $activo->id,
                'Tipo_evento' => 'Cambio',
                'Descripcion' => "evento {$i}",
                'Fecha' => now(),
            ];
        }
        HistorialEvento::insert($filas);

        $this->actingAs($this->admin)
            ->getJson("/activos/{$activo->id}/eventos")
            ->assertOk()
            ->assertJsonCount(200);
    }

    public function test_api_activos_acota_el_tamano_de_pagina(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Activo::create([
                'Nombre_de_activo' => "Equipo {$i}",
                'Etiqueta_activo' => "TBPAG000{$i}",
                'Estado' => 'Disponible',
            ]);
        }

        $respuesta = $this->actingAs($this->admin)
            ->getJson(route('api.activos', ['tamano' => 999999]));

        $respuesta->assertOk()->assertJsonPath('per_page', 100);
    }

    public function test_admin_puede_eliminar_equipo_sin_asignaciones(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop HP',
            'Etiqueta_activo' => 'TBDEL0001',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('activos.destroy', $activo))
            ->assertRedirect(route('activos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ACTIVOS', ['id' => $activo->id]);
    }

    public function test_eliminar_equipo_borra_mantenimientos_y_evidencias_del_disco(): void
    {
        Storage::fake('local');

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBDEL0002',
            'Estado' => 'Disponible',
        ]);
        $mantenimiento = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Limpieza interna',
            'Fecha_mantenimiento' => '2026-10-01',
            'fk_usuario' => $this->admin->id,
            'Creado_el' => now(),
        ]);
        $archivo = 'uploads/mantenimientos/reporte.pdf';
        Storage::disk('local')->put($archivo, 'contenido');
        EvidenciaMantenimiento::create([
            'fk_mantenimiento' => $mantenimiento->id,
            'Ruta_archivo' => $archivo,
            'Nombre_original' => 'reporte.pdf',
            'Tipo_mime' => 'application/pdf',
            'Tamano_bytes' => 1234,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('activos.destroy', $activo))
            ->assertRedirect(route('activos.index'));

        $this->assertDatabaseMissing('ACTIVOS', ['id' => $activo->id]);
        $this->assertDatabaseMissing('MANTENIMIENTOS', ['id' => $mantenimiento->id]);
        $this->assertDatabaseMissing('EVIDENCIAS_MANTENIMIENTO', ['fk_mantenimiento' => $mantenimiento->id]);
        $this->assertTrue(Storage::disk('local')->missing($archivo));
    }

    public function test_no_se_puede_eliminar_equipo_con_asignaciones_registradas(): void
    {
        $persona = Personal::create([
            'Nombre' => 'Luis',
            'Apellido' => 'Martinez',
            'Nombre_usuario' => 'lmartinez',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop HP',
            'Etiqueta_activo' => 'TBDEL0003',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)->post("/activos/{$activo->id}/asignacion", [
            'fk_Personal' => $persona->id,
            'Fecha_asignacion' => '2026-10-08',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->delete(route('activos.destroy', $activo))
            ->assertStatus(409);

        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id]);
    }

    public function test_auditor_no_puede_eliminar_equipo(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Monitor LG',
            'Etiqueta_activo' => 'TBDEL0004',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($auditor)
            ->delete(route('activos.destroy', $activo))
            ->assertForbidden();

        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id]);
    }

    public function test_eliminar_equipo_requiere_sesion(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Teclado Logitech',
            'Etiqueta_activo' => 'TBDEL0005',
            'Estado' => 'Disponible',
        ]);

        $this->delete(route('activos.destroy', $activo))->assertRedirect(route('login'));

        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id]);
    }
}
