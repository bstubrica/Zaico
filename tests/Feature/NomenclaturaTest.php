<?php

namespace Tests\Feature;

use App\Enums\CategoriaActivo;
use App\Models\Activo;
use App\Models\ModeloActivo;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NomenclaturaTest extends TestCase
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
    private function datosActivo(array $extra = []): array
    {
        return array_merge([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBNOM0001',
            'Etiqueta_activo' => 'TBNOM0001',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
        ], $extra);
    }

    public function test_catalogo_canonico_incluye_las_24_categorias_documentadas(): void
    {
        $this->assertSame(25, count(CategoriaActivo::valores()));
        $this->assertContains('Laptop', CategoriaActivo::valores());
        $this->assertContains('Disco Duro / Almacenamiento', CategoriaActivo::valores());
        $this->assertContains('Ratón', CategoriaActivo::valores());
    }

    public function test_canonico_resuelve_sinonimos_y_variantes(): void
    {
        $this->assertSame('Laptop', CategoriaActivo::canonico('notebook'));
        $this->assertSame('Desktop', CategoriaActivo::canonico('PC'));
        $this->assertSame('Ratón', CategoriaActivo::canonico('RATON'));
        $this->assertSame('Router', CategoriaActivo::canonico('Access Point'));
        $this->assertSame('Router', CategoriaActivo::canonico(' ap '));
        $this->assertSame('Disco Duro / Almacenamiento', CategoriaActivo::canonico('SSD'));
        $this->assertNull(CategoriaActivo::canonico('Cachivache'));
        $this->assertNull(CategoriaActivo::canonico('  '));
    }

    public function test_acepta_categoria_canonica_y_nombre_estructurado(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ACTIVOS', [
            'Etiqueta_activo' => 'TBNOM0001',
            'Categoria' => 'Laptop',
        ]);
    }

    public function test_normaliza_sinonimo_de_categoria_al_guardar(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop HP Pavilion TBNOM0002',
                'Etiqueta_activo' => 'TBNOM0002',
                'Categoria' => '  Notebook ',
                'Fabricante' => 'HP',
                'Modelo' => 'Pavilion',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ACTIVOS', [
            'Etiqueta_activo' => 'TBNOM0002',
            'Categoria' => 'Laptop',
        ]);
    }

    public function test_rechaza_categoria_fuera_del_catalogo(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo(['Categoria' => 'Cachivache']))
            ->assertSessionHasErrors('Categoria');

        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBNOM0001']);
    }

    public function test_rechaza_categoria_vacia(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo(['Categoria' => '']))
            ->assertSessionHasErrors('Categoria');
    }

    public function test_rechaza_fabricante_o_modelo_ausentes(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo(['Fabricante' => '']))
            ->assertSessionHasErrors('Fabricante');

        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo(['Modelo' => '']))
            ->assertSessionHasErrors('Modelo');
    }

    public function test_rechaza_nombre_que_no_empieza_por_el_tipo(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Dell Latitude Laptop TBNOM0001',
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_rechaza_nombre_sin_fabricante(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Latitude TBNOM0001',
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_rechaza_nombre_sin_etiqueta(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Dell Latitude',
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_rechaza_componentes_desordenados(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop TBNOM0001 Dell Latitude',
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_rechaza_texto_arbitrario_o_adicional_en_el_nombre(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Gamer Dell Latitude TBNOM0001',
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_acepta_nombre_con_serial_y_lo_exige_si_el_activo_tiene_serial(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Dell Latitude SN-123 TBNOM0003',
                'Etiqueta_activo' => 'TBNOM0003',
                'Serial' => 'SN-123',
            ]))
            ->assertSessionHasNoErrors();

        // Si se provee Serial pero el nombre lo omite, debe fallar por no cumplir la nomenclatura estricta
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Dell Latitude TBNOM0003B',
                'Etiqueta_activo' => 'TBNOM0003B',
                'Serial' => 'SN-123B',
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_serial_se_omite_en_el_nombre_cuando_el_activo_no_tiene_serial(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Dell Latitude TBNOM0004',
                'Etiqueta_activo' => 'TBNOM0004',
                'Serial' => null,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_la_comparacion_del_nombre_respeta_la_composicion_exacta(): void
    {
        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Ratón Logitech M90 TBNOM0006',
                'Etiqueta_activo' => 'TBNOM0006',
                'Categoria' => 'Ratón',
                'Fabricante' => 'Logitech',
                'Modelo' => 'M90',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_usa_el_nombre_del_modelo_del_catalogo_para_validar(): void
    {
        $modelo = ModeloActivo::create(['Nombre' => 'Latitude 5520', 'Fabricante' => 'Dell']);

        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Dell Latitude 5520 TBNOM0007',
                'Etiqueta_activo' => 'TBNOM0007',
                'fk_modelo' => $modelo->id,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_rechaza_nombre_que_no_usa_el_modelo_del_catalogo(): void
    {
        $modelo = ModeloActivo::create(['Nombre' => 'Latitude 5520', 'Fabricante' => 'Dell']);

        $this->actingAs($this->admin)
            ->post('/activos', $this->datosActivo([
                'Nombre_de_activo' => 'Laptop Dell XPS TBNOM0008',
                'Etiqueta_activo' => 'TBNOM0008',
                'fk_modelo' => $modelo->id,
            ]))
            ->assertSessionHasErrors('Nombre_de_activo');
    }

    public function test_edicion_valida_contra_el_estado_resultante(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell Latitude TBNOM0009',
            'Etiqueta_activo' => 'TBNOM0009',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)
            ->put("/activos/{$activo->id}", [
                'Nombre_de_activo' => 'Laptop Dell Latitude TBNOM0009',
                'Categoria' => 'Desktop',
            ])
            ->assertSessionHasErrors('Nombre_de_activo');

        $this->actingAs($this->admin)
            ->put("/activos/{$activo->id}", [
                'Nombre_de_activo' => 'Desktop Dell Latitude TBNOM0009',
                'Categoria' => 'Desktop',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ACTIVOS', [
            'id' => $activo->id,
            'Categoria' => 'Desktop',
            'Nombre_de_activo' => 'Desktop Dell Latitude TBNOM0009',
        ]);
    }

    public function test_editar_activo_legado_no_conforme_falla_hasta_normalizarlo(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo legado sin estructura',
            'Etiqueta_activo' => 'TBLEG0001',
            'Estado' => 'Disponible',
        ]);

        $this->actingAs($this->admin)
            ->put("/activos/{$activo->id}", [
                'Nombre_de_activo' => 'Equipo legado sin estructura',
                'Etiqueta_activo' => 'TBLEG0001',
                'Categoria' => 'Laptop',
                'Fabricante' => 'Dell',
                'Modelo' => 'Latitude',
                'Estado' => 'Disponible',
            ])
            ->assertSessionHasErrors('Nombre_de_activo');

        $this->actingAs($this->admin)
            ->put("/activos/{$activo->id}", [
                'Nombre_de_activo' => 'Laptop Dell Latitude TBLEG0001',
                'Etiqueta_activo' => 'TBLEG0001',
                'Categoria' => 'Laptop',
                'Fabricante' => 'Dell',
                'Modelo' => 'Latitude',
                'Estado' => 'Disponible',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_la_importacion_csv_no_aplica_la_validacion_de_nomenclatura(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo importado libre',
            'Etiqueta_activo' => 'TBCSV0001',
            'Estado' => 'Disponible',
        ]);

        $this->assertDatabaseHas('ACTIVOS', [
            'id' => $activo->id,
            'Categoria' => null,
            'Nombre_de_activo' => 'Equipo importado libre',
        ]);
    }
}
