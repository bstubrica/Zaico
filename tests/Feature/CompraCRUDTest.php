<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Compra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Activo $activo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->activo = Activo::create([
            'Nombre_de_activo' => 'Monitor Samsung',
            'Etiqueta_activo' => 'TBMNT0002',
            'Estado' => 'Disponible',
        ]);
    }

    public function test_admin_puede_registrar_compra(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('activos.compra.store', $this->activo), [
            'Numero_Requisicion' => 'REQ-001',
            'Fecha_Compra' => '2026-09-15',
            'Proveedor' => 'CompuWorld C.A.',
            'Costo_compra' => '245.50',
            'Tiempo_garantia' => '12 meses',
            'Numero_factura' => 'F-88231',
            'Garantia' => 'Cambio directo en tienda',
        ]);

        $respuesta->assertSessionHasNoErrors()
            ->assertRedirect(route('activos.show', $this->activo));

        $this->assertDatabaseHas('COMPRAS', [
            'fk_activo' => $this->activo->id,
            'Proveedor' => 'CompuWorld C.A.',
            'Costo_compra' => '245.50',
        ]);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo_evento' => 'Modificacion',
        ]);
    }

    public function test_registrar_compra_activa_la_actualiza_sin_duplicarla(): void
    {
        $compra = Compra::create([
            'fk_activo' => $this->activo->id,
            'Fecha_Compra' => '2026-09-15',
            'Proveedor' => 'Proveedor original',
        ]);

        $this->actingAs($this->admin)->post(route('activos.compra.store', $this->activo), [
            'Fecha_Compra' => '2026-09-16',
            'Proveedor' => 'Proveedor nuevo',
            'Costo_compra' => '100.00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('COMPRAS', 1);
        $this->assertDatabaseHas('COMPRAS', [
            'id' => $compra->id,
            'Proveedor' => 'Proveedor nuevo',
            'Fecha_Compra' => '2026-09-16',
        ]);
    }

    public function test_admin_puede_eliminar_compra(): void
    {
        Compra::create([
            'fk_activo' => $this->activo->id,
            'Fecha_Compra' => '2026-09-15',
            'Proveedor' => 'Proveedor original',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('activos.compra.destroy', $this->activo))
            ->assertRedirect(route('activos.show', $this->activo));

        $this->assertDatabaseCount('COMPRAS', 0);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo_evento' => 'Modificacion',
            'Descripcion' => 'Compra eliminada',
        ]);
    }

    public function test_datos_de_compra_invalidos_son_rechazados(): void
    {
        $this->actingAs($this->admin)
            ->post(route('activos.compra.store', $this->activo), [
                'Fecha_Compra' => '',
                'Proveedor' => '',
                'Costo_compra' => '-5',
            ])
            ->assertSessionHasErrors(['Fecha_Compra', 'Proveedor', 'Costo_compra']);

        $this->assertDatabaseCount('COMPRAS', 0);
    }

    public function test_auditor_no_puede_modificar_compra(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)
            ->post(route('activos.compra.store', $this->activo), [
                'Fecha_Compra' => '2026-09-15',
                'Proveedor' => 'X',
            ])
            ->assertForbidden();
        $this->actingAs($auditor)
            ->delete(route('activos.compra.destroy', $this->activo))
            ->assertForbidden();
        $this->assertDatabaseCount('COMPRAS', 0);
    }

    public function test_listado_global_de_compras(): void
    {
        Compra::create([
            'fk_activo' => $this->activo->id,
            'Fecha_Compra' => '2026-09-15',
            'Proveedor' => 'Proveedor original',
            'Costo_compra' => '100.00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('compras.index'))
            ->assertOk()
            ->assertSee('Proveedor original');
    }

    public function test_listado_de_compras_requiere_sesion(): void
    {
        $this->get(route('compras.index'))->assertRedirect(route('login'));
    }
}
