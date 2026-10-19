<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\HistorialEvento;
use App\Models\ModeloActivo;
use App\Models\Personal;
use App\Models\Ubicacion;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * Datos mínimos de un activo conforme a la nomenclatura
     * (`Tipo Fabricante Modelo [Serial] Etiqueta`).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datosActivo(array $extra = []): array
    {
        return array_merge([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCOR0999',
            'Etiqueta_activo' => 'TBCOR0999',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
        ], $extra);
    }

    public function test_puede_registrar_activo(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude DL-5520-001 TBCOR0999',
            'Serial' => 'DL-5520-001',
        ]));

        $respuesta->assertRedirect('/activos');
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBCOR0999']);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', ['Tipo_evento' => 'Creacion']);
    }

    public function test_rechaza_categoria_fuera_del_catalogo(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Categoria' => 'Cachivache',
        ]));

        $respuesta->assertSessionHasErrors('Categoria');
        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBCOR0999']);
    }

    public function test_normaliza_sinonimo_de_categoria_al_nombre_canonico(): void
    {
        $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop HP Pavilion TBNOT0001',
            'Etiqueta_activo' => 'TBNOT0001',
            'Categoria' => 'Notebook',
            'Fabricante' => 'HP',
            'Modelo' => 'Pavilion',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBNOT0001', 'Categoria' => 'Laptop']);
    }

    public function test_rechaza_nombre_que_no_respeta_la_estructura(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Equipo sin estructura',
        ]));

        $respuesta->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_etiqueta_duplicada_es_rechazada(): void
    {
        Activo::create(['Nombre_de_activo' => 'X', 'Etiqueta_activo' => 'DUP001', 'Estado' => 'Activo']);

        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Etiqueta_activo' => 'DUP001',
            'Nombre_de_activo' => 'Laptop Dell Latitude DUP001',
        ]));

        $respuesta->assertSessionHasErrors('Etiqueta_activo');
    }

    public function test_acepta_una_direccion_mac_valida(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBMAC0001',
            'Etiqueta_activo' => 'TBMAC0001',
            'Direccion_MAC' => '00:11:22:33:44:55',
        ]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBMAC0001', 'Direccion_MAC' => '00:11:22:33:44:55']);
    }

    public function test_rechaza_una_direccion_mac_invalida(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBMAC0002',
            'Etiqueta_activo' => 'TBMAC0002',
            'Direccion_MAC' => 'no-es-una-mac',
        ]));

        $respuesta->assertSessionHasErrors('Direccion_MAC');
    }

    public function test_rechaza_un_grupo_de_estado_inexistente(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBGRP0001',
            'Etiqueta_activo' => 'TBGRP0001',
            'Estado' => 'Inventado',
        ]));

        $respuesta->assertSessionHasErrors('Estado');
        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBGRP0001']);
    }

    public function test_rechaza_detalle_de_estado_que_no_coincide_con_el_grupo(): void
    {
        $reparacion = EstadoActivo::where('Grupo', 'Reparacion')->firstOrFail();

        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBGRP0002',
            'Etiqueta_activo' => 'TBGRP0002',
            'Estado' => 'Disponible',
            'fk_estado' => $reparacion->id,
        ]));

        $respuesta->assertSessionHasErrors('fk_estado');
    }

    public function test_acepta_detalle_de_estado_coherente(): void
    {
        $disponible = EstadoActivo::where('Grupo', 'Disponible')->firstOrFail();

        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBGRP0003',
            'Etiqueta_activo' => 'TBGRP0003',
            'fk_estado' => $disponible->id,
        ]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBGRP0003', 'fk_estado' => $disponible->id]);
    }

    public function test_actualiza_activo_cambiando_grupo_y_detalle_coherentes(): void
    {
        $disponible = EstadoActivo::where('Grupo', 'Disponible')->firstOrFail();
        $reparacion = EstadoActivo::where('Grupo', 'Reparacion')->firstOrFail();

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBEDT0001',
            'Etiqueta_activo' => 'TBEDT0001',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'fk_estado' => $disponible->id,
        ]);

        $respuesta = $this->actingAs($this->admin)->put("/activos/{$activo->id}", [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBEDT0001',
            'Etiqueta_activo' => 'TBEDT0001',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Reparacion',
            'fk_estado' => $reparacion->id,
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'Estado' => 'Reparacion', 'fk_estado' => $reparacion->id]);
    }

    public function test_denormaliza_ubicacion_y_modelo_desde_el_catalogo(): void
    {
        $ubicacion = Ubicacion::create(['Nombre' => 'Piso 3', 'Estado' => 'Activo']);
        $modelo = ModeloActivo::create(['Nombre' => 'Latitude 5520', 'Fabricante' => 'Dell']);

        $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude 5520 TBCAT0001',
            'Etiqueta_activo' => 'TBCAT0001',
            'Modelo' => 'Latitude 5520',
            'fk_ubicacion' => $ubicacion->id,
            'fk_modelo' => $modelo->id,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ACTIVOS', [
            'Etiqueta_activo' => 'TBCAT0001',
            'fk_ubicacion' => $ubicacion->id,
            'Ubicacion' => 'Piso 3',
            'fk_modelo' => $modelo->id,
            'Modelo' => 'Latitude 5520',
        ]);
    }

    public function test_limpiar_el_catalogo_limpia_el_texto_denormalizado(): void
    {
        $ubicacion = Ubicacion::create(['Nombre' => 'Piso 3', 'Estado' => 'Activo']);

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCAT0002',
            'Etiqueta_activo' => 'TBCAT0002',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'fk_ubicacion' => $ubicacion->id,
            'Ubicacion' => 'Piso 3',
        ]);

        $this->actingAs($this->admin)->put("/activos/{$activo->id}", [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCAT0002',
            'Etiqueta_activo' => 'TBCAT0002',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'fk_ubicacion' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ACTIVOS', [
            'id' => $activo->id,
            'fk_ubicacion' => null,
            'Ubicacion' => null,
        ]);
    }

    public function test_rechaza_una_ubicacion_de_catalogo_inexistente(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosActivo([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCAT0003',
            'Etiqueta_activo' => 'TBCAT0003',
            'fk_ubicacion' => 999999,
        ]));

        $respuesta->assertSessionHasErrors('fk_ubicacion');
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

    public function test_formulario_de_activo_usa_buscadores_en_catalogos(): void
    {
        $ubicacion = Ubicacion::create(['Nombre' => 'Piso 3', 'Estado' => 'Activo']);
        ModeloActivo::create(['Nombre' => 'Latitude 5520', 'Fabricante' => 'Dell']);

        $this->actingAs($this->admin)->get(route('activos.create'))
            ->assertOk()
            ->assertSee('selectorBuscable', false)
            ->assertSee('name="fk_modelo"', false)
            ->assertSee('name="fk_ubicacion"', false)
            ->assertSee('name="fk_estado"', false)
            ->assertSee('name="Ubicacion_Predeterminada"', false)
            ->assertSee('Piso 3');
    }

    public function test_ubicacion_predeterminada_incluye_valor_heredado_fuera_del_catalogo(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo legado',
            'Etiqueta_activo' => 'TBPRED0001',
            'Estado' => 'Disponible',
            'Ubicacion_Predeterminada' => 'N/A',
        ]);

        $this->actingAs($this->admin)->get(route('activos.edit', $activo))
            ->assertOk()
            ->assertSee('name="Ubicacion_Predeterminada"', false)
            ->assertSee('Valor actual');
    }

    public function test_panel_de_asignacion_muestra_buscador_de_personas(): void
    {
        Personal::create([
            'Nombre' => 'Lucia',
            'Apellido' => 'Gomez',
            'Nombre_usuario' => 'lgomez',
            'Cargo' => 'Analista',
            'Estado' => 'Activo',
        ]);
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBBUSC0001',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)->get("/activos/{$activo->id}")
            ->assertOk()
            ->assertSee('selectorBuscable', false)
            ->assertSee('fk_Personal', false)
            ->assertSee('Lucia Gomez');
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

    public function test_index_activos_permite_seleccionar_tamano_de_pagina(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Activo::create([
                'Nombre_de_activo' => sprintf('Equipo Test %02d', $i),
                'Etiqueta_activo' => sprintf('TBPAGE%02d', $i),
                'Estado' => 'Disponible',
            ]);
        }

        $respuesta = $this->actingAs($this->admin)
            ->get(route('activos.index', ['tamano' => 15]));

        $respuesta->assertOk();
        $this->assertCount(15, $respuesta->viewData('activos'));
    }
}
