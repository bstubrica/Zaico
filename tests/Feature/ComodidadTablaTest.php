<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComodidadTablaTest extends TestCase
{
    use RefreshDatabase;

    private function activo(): Activo
    {
        $estado = EstadoActivo::create([
            'Nombre' => 'Disponible',
            'Grupo' => 'Disponible',
            'Deployed' => false,
            'Deployable' => true,
            'Estado' => 1,
        ]);

        return Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBSPLIT01',
            'Categoria' => 'Laptop',
            'Estado' => 'Disponible',
            'fk_estado' => $estado->id,
        ]);
    }

    public function test_escritor_ve_contenedor_ampliado_columna_de_seleccion_y_identidad_fijas(): void
    {
        $this->activo();
        $admin = User::factory()->create(['role' => 'Admin']);

        $html = $this->actingAs($admin)->get(route('activos.index'))->assertOk()->getContent();

        $this->assertStringContainsString('max-w-screen-2xl', $html);
        $this->assertStringContainsString('class="table-col-chk"', $html);
        $this->assertStringContainsString('class="table-col-fija-2"', $html);
    }

    public function test_lector_ve_la_identidad_fija_sin_columna_de_seleccion(): void
    {
        $this->activo();
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $html = $this->actingAs($auditor)->get(route('activos.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="table-col-fija"', $html);
        $this->assertStringNotContainsString('table-col-chk', $html);
        $this->assertStringNotContainsString('table-col-fija-2', $html);
    }
}
