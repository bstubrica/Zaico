<?php

namespace Tests\Feature;

use App\Models\Personal;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Ubicacion $sistemas;

    private Ubicacion $administracion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'Admin']);

        $this->sistemas = Ubicacion::create([
            'Nombre' => 'Tecnología y Sistemas',
            'Estado' => 'Activo',
        ]);

        $this->administracion = Ubicacion::create([
            'Nombre' => 'Administración y Finanzas',
            'Estado' => 'Activo',
        ]);
    }

    public function test_puede_filtrar_personal_por_departamento_ubicacion(): void
    {
        Personal::create([
            'Nombre' => 'Carlos',
            'Apellido' => 'Páez',
            'Nombre_usuario' => 'cpaez',
            'Cargo' => 'Coordinador TI',
            'fk_ubicacion' => $this->sistemas->id,
            'Estado' => 'Activo',
        ]);

        Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Gómez',
            'Nombre_usuario' => 'agomez',
            'Cargo' => 'Contadora',
            'fk_ubicacion' => $this->administracion->id,
            'Estado' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin)->get(route('personal.index', [
            'ubicacion_id' => $this->sistemas->id,
        ]));

        $response->assertOk();
        $response->assertSee('Carlos');
        $response->assertDontSee('Ana Gómez');
    }

    public function test_puede_filtrar_personal_sin_ubicacion_asignada(): void
    {
        Personal::create([
            'Nombre' => 'Persona',
            'Apellido' => 'Con Ubicacion',
            'Nombre_usuario' => 'conubi',
            'fk_ubicacion' => $this->sistemas->id,
            'Estado' => 'Activo',
        ]);

        Personal::create([
            'Nombre' => 'Persona',
            'Apellido' => 'Sin Ubicacion',
            'Nombre_usuario' => 'sinubi',
            'fk_ubicacion' => null,
            'Estado' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin)->get(route('personal.index', [
            'ubicacion_id' => 'sin_ubicacion',
        ]));

        $response->assertOk();
        $response->assertSee('Sin Ubicacion');
        $response->assertDontSee('Con Ubicacion');
    }

    public function test_puede_filtrar_personal_por_cargo(): void
    {
        Personal::create([
            'Nombre' => 'Luisa',
            'Apellido' => 'Ramos',
            'Nombre_usuario' => 'lramos',
            'Cargo' => 'Desarrolladora',
            'Estado' => 'Activo',
        ]);

        Personal::create([
            'Nombre' => 'Marcos',
            'Apellido' => 'Torres',
            'Nombre_usuario' => 'mtorres',
            'Cargo' => 'Soporte Técnico',
            'Estado' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin)->get(route('personal.index', [
            'cargo' => 'Desarrolladora',
        ]));

        $response->assertOk();
        $response->assertSee('Luisa');
        $response->assertDontSee('Marcos Torres');
    }

    public function test_crear_personal_con_departamento_y_cargo(): void
    {
        $response = $this->actingAs($this->admin)->post(route('personal.store'), [
            'Nombre' => 'Gabriel',
            'Apellido' => 'Mendoza',
            'Nombre_usuario' => 'gmendoza',
            'Cargo' => 'Auditor TI',
            'fk_ubicacion' => $this->sistemas->id,
        ]);

        $response->assertRedirect(route('personal.index'));

        $this->assertDatabaseHas('PERSONAL', [
            'Nombre_usuario' => 'gmendoza',
            'Cargo' => 'Auditor TI',
            'fk_ubicacion' => $this->sistemas->id,
        ]);
    }

    public function test_actualizar_personal_con_departamento(): void
    {
        $persona = Personal::create([
            'Nombre' => 'Elena',
            'Apellido' => 'Ruiz',
            'Nombre_usuario' => 'eruiz',
            'Cargo' => 'Asistente',
            'Estado' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin)->put(route('personal.update', $persona), [
            'Nombre' => 'Elena',
            'Apellido' => 'Ruiz',
            'Nombre_usuario' => 'eruiz',
            'Cargo' => 'Supervisora',
            'fk_ubicacion' => $this->administracion->id,
        ]);

        $response->assertRedirect(route('personal.index'));

        $this->assertDatabaseHas('PERSONAL', [
            'id' => $persona->id,
            'Cargo' => 'Supervisora',
            'fk_ubicacion' => $this->administracion->id,
        ]);
    }

    public function test_reporte_csv_personal_incluye_departamento_y_respeta_filtros(): void
    {
        Personal::create([
            'Nombre' => 'Felipe',
            'Apellido' => 'Rivas',
            'Nombre_usuario' => 'frivas',
            'Cargo' => 'Especialista',
            'fk_ubicacion' => $this->sistemas->id,
            'Estado' => 'Activo',
        ]);

        Personal::create([
            'Nombre' => 'Diana',
            'Apellido' => 'Soto',
            'Nombre_usuario' => 'dsoto',
            'Cargo' => 'Secretaria',
            'fk_ubicacion' => $this->administracion->id,
            'Estado' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin)->get(route('reportes.personal', [
            'ubicacion_id' => $this->sistemas->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Departamento / Ubicación', $response->streamedContent());
        $this->assertStringContainsString('Felipe', $response->streamedContent());
        $this->assertStringContainsString('Tecnología y Sistemas', $response->streamedContent());
        $this->assertStringNotContainsString('Diana', $response->streamedContent());
    }
}
