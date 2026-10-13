<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusquedaFiltrosTest extends TestCase
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
    private function activo(string $nombre, string $etiqueta, array $extra = []): Activo
    {
        return Activo::create(array_merge([
            'Nombre_de_activo' => $nombre,
            'Etiqueta_activo' => $etiqueta,
            'Estado' => 'Disponible',
        ], $extra));
    }

    private function persona(string $nombre, string $apellido, string $usuario, array $extra = []): Personal
    {
        return Personal::create(array_merge([
            'Nombre' => $nombre,
            'Apellido' => $apellido,
            'Nombre_usuario' => $usuario,
            'Estado' => 'Activo',
        ], $extra));
    }

    public function test_busqueda_de_activos_es_multipalabra_y_sin_distinguir_mayusculas(): void
    {
        $this->activo('Samsung Galaxy', 'TBMULTI1', ['Modelo' => 'A54']);
        $this->activo('Laptop HP', 'TBMULTI2');

        $this->actingAs($this->admin)
            ->get(route('activos.index', ['termino' => 'samsung A54']))
            ->assertOk()
            ->assertSee('TBMULTI1')
            ->assertDontSee('TBMULTI2');
    }

    public function test_filtra_activos_por_ubicacion_parcial(): void
    {
        $this->activo('PC Uno', 'TBUBI1', ['Ubicacion' => 'Piso 2 - Administracion']);
        $this->activo('PC Dos', 'TBUBI2', ['Ubicacion' => 'Piso 1 - Soporte']);

        $this->actingAs($this->admin)
            ->get(route('activos.index', ['ubicacion' => 'piso 2']))
            ->assertOk()
            ->assertSee('TBUBI1')
            ->assertDontSee('TBUBI2');
    }

    public function test_filtra_activos_por_persona_asignada_con_nombre_completo(): void
    {
        $carlos = $this->persona('Carlos', 'Páez', 'cpaez');
        $ana = $this->persona('Ana', 'López', 'alopez');

        $asignado = $this->activo('Laptop Dell', 'TBASG1');
        $otro = $this->activo('Laptop HP', 'TBASG2');

        Asignacion::create(['fk_Activo' => $asignado->id, 'fk_Personal' => $carlos->id, 'fk_usuario' => $this->admin->id, 'Fecha_asignacion' => '2026-01-10', 'Estado' => 'Activa']);
        Asignacion::create(['fk_Activo' => $otro->id, 'fk_Personal' => $ana->id, 'fk_usuario' => $this->admin->id, 'Fecha_asignacion' => '2026-01-10', 'Estado' => 'Activa']);

        $this->actingAs($this->admin)
            ->get(route('activos.index', ['asignado_a' => 'carlos páez']))
            ->assertOk()
            ->assertSee('TBASG1')
            ->assertDontSee('TBASG2');
    }

    public function test_filtra_activos_por_estado_aunque_no_tengan_catalogo(): void
    {
        $reparacion = EstadoActivo::create(['Nombre' => 'Reparacion (pending)', 'Grupo' => 'Reparacion', 'Deployed' => true, 'Deployable' => false, 'Estado' => 1]);
        $disponible = EstadoActivo::create(['Nombre' => 'Disponible (deployable)', 'Grupo' => 'Disponible', 'Deployed' => false, 'Deployable' => true, 'Estado' => 1]);

        $this->activo('Equipo sin catalogo', 'TBEST1', ['Estado' => 'Reparacion', 'fk_estado' => null]);
        $this->activo('Equipo con catalogo', 'TBEST2', ['Estado' => 'Reparacion', 'fk_estado' => $reparacion->id]);
        $this->activo('Equipo disponible', 'TBEST3', ['Estado' => 'Disponible', 'fk_estado' => $disponible->id]);

        $this->actingAs($this->admin)
            ->get(route('activos.index', ['estado' => 'Reparacion']))
            ->assertOk()
            ->assertSee('TBEST1')
            ->assertSee('TBEST2')
            ->assertDontSee('TBEST3');
    }

    public function test_busqueda_de_personal_multipalabra_y_filtro_por_estado(): void
    {
        $this->persona('Glenire', 'Rodriguez', 'grodriguez');
        $this->persona('Mario', 'Suarez', 'msuarez', ['Estado' => 'Inactivo']);

        $this->actingAs($this->admin)
            ->get(route('personal.index', ['termino' => 'glenire rodriguez']))
            ->assertOk()
            ->assertSee('Rodriguez')
            ->assertDontSee('Suarez');

        $this->actingAs($this->admin)
            ->get(route('personal.index', ['estado' => 'Inactivo']))
            ->assertOk()
            ->assertSee('Suarez')
            ->assertDontSee('Rodriguez');
    }

    public function test_filtra_mantenimientos_por_equipo_fechas_y_tolera_fechas_invalidas(): void
    {
        $epson = $this->activo('Impresora Epson', 'TBMANT1');
        $hp = $this->activo('Laptop HP', 'TBMANT2');

        Mantenimiento::create(['fk_activo' => $epson->id, 'Tipo' => 'Preventivo', 'Descripcion' => 'Limpieza', 'Fecha_mantenimiento' => '2026-03-15', 'fk_usuario' => $this->admin->id]);
        Mantenimiento::create(['fk_activo' => $hp->id, 'Tipo' => 'Correctivo', 'Descripcion' => 'Cambio teclado', 'Fecha_mantenimiento' => '2026-01-05', 'fk_usuario' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('mantenimientos.index', ['activo' => 'epson']))
            ->assertOk()
            ->assertSee('TBMANT1')
            ->assertDontSee('TBMANT2');

        $this->actingAs($this->admin)
            ->get(route('mantenimientos.index', ['desde' => '2026-03-01', 'hasta' => '2026-03-31']))
            ->assertOk()
            ->assertSee('TBMANT1')
            ->assertDontSee('TBMANT2');

        $this->actingAs($this->admin)
            ->get(route('mantenimientos.index', ['desde' => 'no-es-fecha', 'hasta' => '2026-13-40']))
            ->assertOk()
            ->assertSee('TBMANT1')
            ->assertSee('TBMANT2');
    }

    public function test_filtra_historial_por_persona_fechas_y_tolera_entradas_invalidas(): void
    {
        $carlos = $this->persona('Carlos', 'Páez', 'cpaez');
        $activo = $this->activo('Laptop Dell', 'TBHIST1');

        Asignacion::create(['fk_Activo' => $activo->id, 'fk_Personal' => $carlos->id, 'fk_usuario' => $this->admin->id, 'Fecha_asignacion' => '2026-02-01', 'Estado' => 'Activa']);

        $this->actingAs($this->admin)
            ->get(route('historial.index', ['personal_id' => $carlos->id, 'desde' => '2026-01-01', 'hasta' => '2026-12-31']))
            ->assertOk()
            ->assertSee('TBHIST1');

        $this->actingAs($this->admin)
            ->get(route('historial.index', ['personal_id' => 'abc', 'desde' => 'ayer', 'hasta' => '2026-99-99']))
            ->assertOk()
            ->assertSee('TBHIST1');
    }
}
