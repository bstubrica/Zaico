<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\TarjetaRed;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServidorTest extends TestCase
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datosServidor(array $extra = []): array
    {
        return array_merge([
            'Nombre_de_activo' => 'Servidor Dell PowerEdge TBSRV0001',
            'Etiqueta_activo' => 'TBSRV0001',
            'Categoria' => 'Servidor',
            'Fabricante' => 'Dell',
            'Modelo' => 'PowerEdge',
            'Estado' => 'Disponible',
            'IP' => '10.0.0.5',
            'Sistema_Operativo' => 'Windows Server 2019',
            'Memoria_RAM' => '32 GB',
            'Capacidad_Disco' => '2 x 1 TB SSD',
            'Estatus_Servidor' => 'Encendido',
        ], $extra);
    }

    private function switchDeInventario(string $nombre = 'Switch Piso 2', string $etiqueta = 'TBSW0001'): Activo
    {
        return Activo::create([
            'Nombre_de_activo' => $nombre,
            'Etiqueta_activo' => $etiqueta,
            'Categoria' => 'SWITCH',
            'Estado' => 'Disponible',
        ]);
    }

    public function test_registra_servidor_con_datos_y_tarjetas_de_red(): void
    {
        $switch = $this->switchDeInventario();

        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [
                [
                    'Numero' => 1,
                    'Nombre' => 'Ethernet 1',
                    'Direccion_MAC' => '00:11:22:33:44:55',
                    'IP' => '10.0.0.5',
                    'fk_switch' => $switch->id,
                    'Puerto' => 'Gi0/1',
                ],
                [
                    'Numero' => 2,
                    'Nombre' => 'Ethernet 2',
                    'fk_switch' => '',
                    'Puerto' => '',
                ],
            ],
        ]));

        $respuesta->assertRedirect('/activos');

        $servidor = Activo::where('Etiqueta_activo', 'TBSRV0001')->firstOrFail();

        $this->assertSame('10.0.0.5', $servidor->IP);
        $this->assertSame('Windows Server 2019', $servidor->Sistema_Operativo);
        $this->assertSame('32 GB', $servidor->Memoria_RAM);
        $this->assertSame('2 x 1 TB SSD', $servidor->Capacidad_Disco);
        $this->assertSame('Encendido', $servidor->Estatus_Servidor);
        $this->assertTrue($servidor->esServidor());

        $this->assertDatabaseHas('TARJETAS_RED', [
            'fk_activo' => $servidor->id,
            'Numero' => 1,
            'fk_switch' => $switch->id,
            'Switch' => 'Switch Piso 2',
            'Puerto' => 'Gi0/1',
        ]);
        $this->assertDatabaseHas('TARJETAS_RED', [
            'fk_activo' => $servidor->id,
            'Numero' => 2,
            'fk_switch' => null,
            'Switch' => null,
        ]);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $servidor->id,
            'Tipo_evento' => 'Modificacion',
        ]);
    }

    public function test_actualizar_tarjetas_reemplaza_y_elimina_las_ausentes(): void
    {
        $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [
                ['Numero' => 1, 'Nombre' => 'Ethernet 1'],
                ['Numero' => 2, 'Nombre' => 'Ethernet 2'],
            ],
        ]))->assertSessionHasNoErrors();

        $servidor = Activo::where('Etiqueta_activo', 'TBSRV0001')->firstOrFail();

        $this->actingAs($this->admin)->put("/activos/{$servidor->id}", $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [
                ['Numero' => 2, 'Nombre' => 'Ethernet 2 renombrada'],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('TARJETAS_RED', ['fk_activo' => $servidor->id, 'Numero' => 1]);
        $this->assertDatabaseHas('TARJETAS_RED', [
            'fk_activo' => $servidor->id,
            'Numero' => 2,
            'Nombre' => 'Ethernet 2 renombrada',
        ]);
        $this->assertSame(1, TarjetaRed::where('fk_activo', $servidor->id)->count());
    }

    public function test_guardar_sin_el_bloque_de_servidor_no_borra_las_tarjetas(): void
    {
        $servidor = Activo::create($this->datosServidor([
            'Nombre_de_activo' => 'Servidor Dell PowerEdge TBSRV0002',
            'Etiqueta_activo' => 'TBSRV0002',
        ]));

        TarjetaRed::create([
            'fk_activo' => $servidor->id,
            'Numero' => 1,
            'Nombre' => 'Ethernet 1',
        ]);

        $this->actingAs($this->admin)->put("/activos/{$servidor->id}", [
            'Nombre_de_activo' => 'Servidor Dell PowerEdge TBSRV0002',
            'Etiqueta_activo' => 'TBSRV0002',
            'Categoria' => 'Servidor',
            'Fabricante' => 'Dell',
            'Modelo' => 'PowerEdge',
            'Estado' => 'Disponible',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TarjetaRed::where('fk_activo', $servidor->id)->count());
        $this->assertSame('10.0.0.5', $servidor->fresh()->IP);
    }

    public function test_cambiar_de_categoria_conserva_datos_y_tarjetas(): void
    {
        $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [['Numero' => 1, 'Nombre' => 'Ethernet 1']],
        ]))->assertSessionHasNoErrors();

        $servidor = Activo::where('Etiqueta_activo', 'TBSRV0001')->firstOrFail();

        $this->actingAs($this->admin)->put("/activos/{$servidor->id}", $this->datosServidor([
            'Nombre_de_activo' => 'Laptop Dell PowerEdge TBSRV0001',
            'Categoria' => 'Laptop',
            'tarjetas_presente' => 1,
            'tarjetas' => [['Numero' => 1, 'Nombre' => 'Ethernet 1']],
        ]))->assertSessionHasNoErrors();

        $servidor->refresh();

        $this->assertSame('Laptop', $servidor->Categoria);
        $this->assertFalse($servidor->esServidor());
        $this->assertSame('10.0.0.5', $servidor->IP);
        $this->assertSame('Encendido', $servidor->Estatus_Servidor);
        $this->assertSame(1, TarjetaRed::where('fk_activo', $servidor->id)->count());
    }

    public function test_la_edicion_del_servidor_conserva_la_ip_del_bloque_general(): void
    {
        $servidor = Activo::create($this->datosServidor([
            'Nombre_de_activo' => 'Servidor Dell PowerEdge TBSRV0003',
            'Etiqueta_activo' => 'TBSRV0003',
        ]));

        // El bloque de servidor permanece en el DOM (lo oculta Alpine): si volviera
        // a pintar un `name="IP"`, PHP conservaría el último valor enviado y el
        // servidor se quedaría sin la IP capturada en el bloque general.
        $this->actingAs($this->admin)->put("/activos/{$servidor->id}", $this->datosServidor([
            'Nombre_de_activo' => 'Servidor Dell PowerEdge TBSRV0003',
            'Etiqueta_activo' => 'TBSRV0003',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('10.0.0.5', $servidor->fresh()->IP);
    }

    public function test_valida_ip_de_servidor_y_mac_de_tarjeta(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'IP' => 'no-es-una-ip',
            'tarjetas_presente' => 1,
            'tarjetas' => [['Numero' => 1, 'Direccion_MAC' => 'no-es-mac']],
        ]));

        $respuesta->assertSessionHasErrors(['IP', 'tarjetas.0.Direccion_MAC']);
        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBSRV0001']);
    }

    public function test_rechaza_numeros_de_tarjeta_repetidos(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [
                ['Numero' => 1, 'Nombre' => 'Ethernet 1'],
                ['Numero' => 1, 'Nombre' => 'Ethernet 2'],
            ],
        ]));

        $respuesta->assertSessionHasErrors('tarjetas.1.Numero');
    }

    public function test_rechaza_mas_de_tarjetas_que_el_maximo(): void
    {
        $tarjetas = [];

        for ($numero = 1; $numero <= 9; $numero++) {
            $tarjetas[] = ['Numero' => $numero, 'Nombre' => "Ethernet {$numero}"];
        }

        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => $tarjetas,
        ]));

        $respuesta->assertSessionHasErrors('tarjetas');
    }

    public function test_rechaza_un_switch_inexistente(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [['Numero' => 1, 'fk_switch' => 999999]],
        ]));

        $respuesta->assertSessionHasErrors('tarjetas.0.fk_switch');
    }

    public function test_descarta_filas_de_tarjeta_vacias(): void
    {
        $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [
                ['Numero' => '', 'Nombre' => '', 'Direccion_MAC' => '', 'IP' => '', 'fk_switch' => '', 'Puerto' => ''],
                ['Numero' => 1, 'Nombre' => 'Ethernet 1'],
            ],
        ]))->assertSessionHasNoErrors();

        $servidor = Activo::where('Etiqueta_activo', 'TBSRV0001')->firstOrFail();

        $this->assertSame(1, TarjetaRed::where('fk_activo', $servidor->id)->count());
    }

    public function test_filtra_el_listado_por_ip(): void
    {
        Activo::create([
            'Nombre_de_activo' => 'Servidor Alfa',
            'Etiqueta_activo' => 'TBIP0001',
            'Categoria' => 'Servidor',
            'Estado' => 'Disponible',
            'IP' => '10.0.0.5',
        ]);
        Activo::create([
            'Nombre_de_activo' => 'Servidor Beta',
            'Etiqueta_activo' => 'TBIP0002',
            'Categoria' => 'Servidor',
            'Estado' => 'Disponible',
            'IP' => '10.0.1.9',
        ]);

        $this->actingAs($this->admin)->get('/activos?ip=10.0.0.5')
            ->assertOk()
            ->assertSee('Servidor Alfa')
            ->assertDontSee('Servidor Beta');
    }

    public function test_la_ficha_muestra_el_panel_de_servidor(): void
    {
        $switch = $this->switchDeInventario();

        $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [['Numero' => 1, 'Nombre' => 'Ethernet 1', 'fk_switch' => $switch->id]],
        ]))->assertSessionHasNoErrors();

        $servidor = Activo::where('Etiqueta_activo', 'TBSRV0001')->firstOrFail();

        $this->actingAs($this->admin)->get("/activos/{$servidor->id}")
            ->assertOk()
            ->assertSee('Servidor')
            ->assertSee('Tarjetas de red')
            ->assertSee('10.0.0.5')
            ->assertSee('Switch Piso 2');

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop comun',
            'Etiqueta_activo' => 'TBLAP0001',
            'Categoria' => 'Laptop',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)->get("/activos/{$activo->id}")
            ->assertOk()
            ->assertDontSee('Tarjetas de red');
    }

    public function test_formulario_incluye_el_bloque_dinamico_de_servidor(): void
    {
        $this->switchDeInventario();

        $this->actingAs($this->admin)->get(route('activos.create'))
            ->assertOk()
            ->assertSee('panelServidor', false)
            ->assertSee('name="tarjetas_presente"', false)
            ->assertSee('name="IP"', false)
            ->assertSee('id="servidor-filas-tarjetas"', false)
            ->assertSee('id="servidor-plantilla-tarjeta"', false)
            ->assertSee('servidor-plantilla-tarjeta', false)
            ->assertSee('tarjetas[__tarjeta__][Numero]', false)
            ->assertSee('Switch Piso 2')
            ->assertSee('En mantenimiento');
    }

    public function test_edicion_muestra_las_tarjetas_guardadas(): void
    {
        $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [['Numero' => 7, 'Nombre' => 'Ethernet 7', 'Puerto' => 'Gi0/7']],
        ]))->assertSessionHasNoErrors();

        $servidor = Activo::where('Etiqueta_activo', 'TBSRV0001')->firstOrFail();

        $this->actingAs($this->admin)->get(route('activos.edit', $servidor))
            ->assertOk()
            ->assertSee('tarjetas[0][Numero]', false)
            ->assertSee('Gi0/7');
    }

    public function test_el_csv_de_activos_incluye_los_datos_de_servidor(): void
    {
        Activo::create($this->datosServidor([
            'Nombre_de_activo' => 'Servidor CSV',
            'Etiqueta_activo' => 'TBSRV0003',
        ]));

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.activos'));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('"Dirección MAC";IP;"Sistema operativo";"Memoria RAM";"Capacidad de disco";Estatus', $contenido);
        $this->assertStringContainsString('10.0.0.5', $contenido);
        $this->assertStringContainsString('Windows Server 2019', $contenido);
        $this->assertStringContainsString('Encendido', $contenido);
    }

    public function test_las_vistas_de_power_bi_incluyen_los_datos_de_servidor(): void
    {
        $switch = $this->switchDeInventario();

        $this->actingAs($this->admin)->post('/activos', $this->datosServidor([
            'tarjetas_presente' => 1,
            'tarjetas' => [
                [
                    'Numero' => 1,
                    'Nombre' => 'Ethernet 1',
                    'Direccion_MAC' => '00:11:22:33:44:55',
                    'fk_switch' => $switch->id,
                    'Puerto' => 'Gi0/1',
                ],
            ],
        ]))->assertSessionHasNoErrors();

        $fila = DB::table('vw_inventario')->where('Etiqueta_activo', 'TBSRV0001')->first();

        $this->assertNotNull($fila);
        $this->assertSame('10.0.0.5', $fila->IP);
        $this->assertSame('Windows Server 2019', $fila->Sistema_Operativo);
        $this->assertSame('32 GB', $fila->Memoria_RAM);
        $this->assertSame('2 x 1 TB SSD', $fila->Capacidad_Disco);
        $this->assertSame('Encendido', $fila->Estatus_Servidor);

        $tarjeta = DB::table('vw_tarjetas_red')->where('Etiqueta_activo', 'TBSRV0001')->first();

        $this->assertNotNull($tarjeta);
        $this->assertSame('Servidor Dell PowerEdge TBSRV0001', $tarjeta->Nombre_de_activo);
        $this->assertSame('10.0.0.5', $tarjeta->IP_servidor);
        $this->assertSame(1, (int) $tarjeta->Numero);
        $this->assertSame('00:11:22:33:44:55', $tarjeta->Direccion_MAC);
        $this->assertSame('Switch Piso 2', $tarjeta->Switch);
        $this->assertSame('Gi0/1', $tarjeta->Puerto);
    }

    public function test_clasifica_categorias_de_servidor_y_switch_sin_distinguir_formato(): void
    {
        $this->assertTrue(Activo::categoriaEsServidor('Servidor'));
        $this->assertTrue(Activo::categoriaEsServidor('  SERVIDORES '));
        $this->assertFalse(Activo::categoriaEsServidor('Switch'));
        $this->assertFalse(Activo::categoriaEsServidor(null));
        $this->assertFalse(Activo::categoriaEsServidor('   '));

        $this->assertTrue(Activo::categoriaEsSwitch('SWITCH'));
        $this->assertTrue(Activo::categoriaEsSwitch(' switches '));
        $this->assertFalse(Activo::categoriaEsSwitch('Servidor'));
    }

    public function test_auditor_no_puede_registrar_servidores(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->post('/activos', $this->datosServidor())->assertForbidden();
        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBSRV0001']);
    }
}
