<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusquedaRapidaTest extends TestCase
{
    use RefreshDatabase;

    private function activo(string $etiqueta, string $serial): Activo
    {
        return Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => $etiqueta,
            'Serial' => $serial,
            'Categoria' => 'Laptop',
            'Estado' => 'Disponible',
        ]);
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('activos.buscar'))->assertRedirect(route('login'));
    }

    public function test_muestra_el_formulario_sin_codigo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activos.buscar'))
            ->assertOk()
            ->assertSee('Búsqueda rápida');
    }

    public function test_encuentra_por_etiqueta_sin_distinguir_mayusculas(): void
    {
        $user = User::factory()->create();
        $activo = $this->activo('TBSM0001', 'SN-999');

        $this->actingAs($user)
            ->get(route('activos.buscar', ['codigo' => 'tbsm0001']))
            ->assertRedirect(route('activos.show', $activo));
    }

    public function test_encuentra_por_serial(): void
    {
        $user = User::factory()->create();
        $activo = $this->activo('TBSM0002', 'SN-ABC-123');

        $this->actingAs($user)
            ->get(route('activos.buscar', ['codigo' => 'sn-abc-123']))
            ->assertRedirect(route('activos.show', $activo));
    }

    public function test_ignora_espacios_alrededor_del_codigo(): void
    {
        $user = User::factory()->create();
        $activo = $this->activo('TBSM0003', 'SN-777');

        $this->actingAs($user)
            ->get(route('activos.buscar', ['codigo' => '  TBSM0003  ']))
            ->assertRedirect(route('activos.show', $activo));
    }

    public function test_codigo_no_encontrado_muestra_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activos.buscar', ['codigo' => 'NO-EXISTE']))
            ->assertRedirect(route('activos.buscar'))
            ->assertSessionHasErrors('codigo');
    }
}
