<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\ModeloActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeloActivoCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    public function test_admin_puede_registrar_modelo(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/modelos', [
            'Nombre' => 'Latitude 5520',
            'Fabricante' => 'Dell',
            'Categoria' => 'Laptop',
            'Estado' => 'Activo',
        ]);

        $respuesta->assertRedirect('/modelos');
        $this->assertDatabaseHas('MODELOS_ACTIVO', ['Nombre' => 'Latitude 5520', 'Fabricante' => 'Dell']);
    }

    public function test_nombre_duplicado_es_rechazado(): void
    {
        ModeloActivo::create(['Nombre' => 'XPS 13', 'Fabricante' => 'Dell']);

        $respuesta = $this->actingAs($this->admin)->post('/modelos', [
            'Nombre' => 'XPS 13',
        ]);

        $respuesta->assertSessionHasErrors('Nombre');
    }

    public function test_actualiza_un_modelo(): void
    {
        $modelo = ModeloActivo::create(['Nombre' => 'ThinkPad', 'Fabricante' => 'Lenovo']);

        $respuesta = $this->actingAs($this->admin)->put("/modelos/{$modelo->id}", [
            'Nombre' => 'ThinkPad T14',
            'Fabricante' => 'Lenovo',
            'Categoria' => 'Laptop',
            'Estado' => 'Activo',
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('MODELOS_ACTIVO', ['id' => $modelo->id, 'Nombre' => 'ThinkPad T14']);
    }

    public function test_la_baja_es_logica_y_conserva_la_fila(): void
    {
        $modelo = ModeloActivo::create(['Nombre' => 'OptiPlex', 'Fabricante' => 'Dell', 'Estado' => 'Activo']);
        $activo = Activo::create([
            'Nombre_de_activo' => 'PC de escritorio',
            'Etiqueta_activo' => 'TBMOD0001',
            'Estado' => 'Disponible',
            'Modelo' => 'OptiPlex',
            'fk_modelo' => $modelo->id,
        ]);

        $this->actingAs($this->admin)->delete("/modelos/{$modelo->id}")
            ->assertRedirect('/modelos');

        $this->assertDatabaseHas('MODELOS_ACTIVO', ['id' => $modelo->id, 'Estado' => 'Inactivo']);
        $this->assertDatabaseHas('ACTIVOS', ['id' => $activo->id, 'fk_modelo' => $modelo->id]);
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->post('/modelos', ['Nombre' => 'X'])->assertForbidden();
    }

    public function test_sin_sesion_redirige_a_login(): void
    {
        $this->get('/modelos')->assertRedirect(route('login'));
    }

    public function test_auditor_no_puede_consultar_el_catalogo(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get('/modelos')->assertForbidden();
    }
}
