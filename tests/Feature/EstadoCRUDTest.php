<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadoCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $soporte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->soporte = User::factory()->create(['role' => 'SoporteTecnico']);
    }

    public function test_admin_puede_crear_estado(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('estados.store'), [
            'Nombre' => 'En revisión',
            'Grupo' => 'Reparacion',
            'Deployed' => '1',
            'Deployable' => '0',
            'Estado' => '1',
        ]);

        $respuesta->assertSessionHasNoErrors()
            ->assertRedirect(route('estados.index'));

        $this->assertDatabaseHas('ESTADOS_ACTIVO', [
            'Nombre' => 'En revisión',
            'Grupo' => 'Reparacion',
            'Deployed' => true,
            'Deployable' => false,
            'Estado' => 1,
        ]);
    }

    public function test_crear_estado_duplicado_es_rechazado(): void
    {
        EstadoActivo::create(['Nombre' => 'En uso', 'Grupo' => 'Asignado', 'Deployed' => true, 'Deployable' => true, 'Estado' => 1]);

        $this->actingAs($this->admin)
            ->post(route('estados.store'), ['Nombre' => 'En uso', 'Grupo' => 'Asignado'])
            ->assertSessionHasErrors('Nombre');

        $this->assertDatabaseCount('ESTADOS_ACTIVO', 1);
    }

    public function test_admin_puede_editar_estado(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'En uso', 'Grupo' => 'Asignado', 'Deployed' => true, 'Deployable' => true, 'Estado' => 1]);

        $respuesta = $this->actingAs($this->admin)->put(route('estados.update', $estado), [
            'Nombre' => 'En uso (cambiado)',
            'Grupo' => 'Disponible',
            'Deployed' => '0',
            'Deployable' => '1',
            'Estado' => '0',
        ]);

        $respuesta->assertSessionHasNoErrors()
            ->assertRedirect(route('estados.index'));

        $this->assertDatabaseHas('ESTADOS_ACTIVO', [
            'id' => $estado->id,
            'Nombre' => 'En uso (cambiado)',
            'Grupo' => 'Disponible',
            'Deployed' => false,
            'Deployable' => true,
            'Estado' => 0,
        ]);
    }

    public function test_admin_puede_eliminar_estado_sin_activos(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'Sin uso', 'Grupo' => 'Disponible', 'Estado' => 1]);

        $this->actingAs($this->admin)
            ->delete(route('estados.destroy', $estado))
            ->assertRedirect(route('estados.index'));

        $this->assertDatabaseMissing('ESTADOS_ACTIVO', ['id' => $estado->id]);
    }

    public function test_no_se_puede_eliminar_estado_en_uso_por_activos(): void
    {
        $estado = EstadoActivo::create(['Nombre' => 'En uso', 'Grupo' => 'Asignado', 'Estado' => 1]);
        Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBMNT0001',
            'Estado' => 'Asignado',
            'fk_estado' => $estado->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('estados.destroy', $estado))
            ->assertStatus(409);

        $this->assertDatabaseHas('ESTADOS_ACTIVO', ['id' => $estado->id]);
    }

    public function test_solo_admin_gestiona_el_catalogo(): void
    {
        $this->actingAs($this->soporte)->get(route('estados.create'))->assertForbidden();
        $this->actingAs($this->soporte)->get(route('estados.index'))->assertOk();

        $estado = EstadoActivo::create(['Nombre' => 'En uso', 'Grupo' => 'Asignado', 'Estado' => 1]);

        $this->actingAs($this->soporte)
            ->post(route('estados.store'), ['Nombre' => 'X', 'Grupo' => 'Asignado'])
            ->assertForbidden();
        $this->actingAs($this->soporte)
            ->put(route('estados.update', $estado), ['Nombre' => 'Y', 'Grupo' => 'Asignado'])
            ->assertForbidden();
        $this->actingAs($this->soporte)
            ->delete(route('estados.destroy', $estado))
            ->assertForbidden();

        $this->actingAs($this->admin)->get(route('estados.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('estados.index'))->assertOk()->assertSee('Nuevo estado');
    }

    public function test_eliminar_estado_requiere_sesion(): void
    {
        $this->get(route('estados.create'))->assertRedirect(route('login'));
        $this->post(route('estados.store'), ['Nombre' => 'X', 'Grupo' => 'Asignado'])->assertRedirect(route('login'));
        $this->put(route('estados.update', 1), ['Nombre' => 'X', 'Grupo' => 'Asignado'])->assertRedirect(route('login'));
        $this->delete(route('estados.destroy', 1))->assertRedirect(route('login'));
    }
}
