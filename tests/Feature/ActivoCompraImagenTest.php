<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Compra;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivoCompraImagenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Activo $activo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->seed(EstadoSeeder::class);
        $this->activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBCMP0001',
            'Estado' => 'Disponible',
        ]);
    }

    public function test_admin_puede_registrar_datos_de_compra(): void
    {
        $respuesta = $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'Numero_Requisicion' => 'REQ-001',
            'Numero_factura' => 'FAC-777',
            'Proveedor' => 'Dell Costa Rica',
            'Fecha_Compra' => '2026-10-01',
            'Costo_compra' => '1500.50',
            'Tiempo_garantia' => '12 meses',
            'Garantia' => 'Garantía de fábrica',
        ]);

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('COMPRAS', [
            'fk_activo' => $this->activo->id,
            'Numero_Requisicion' => 'REQ-001',
            'Numero_factura' => 'FAC-777',
            'Proveedor' => 'Dell Costa Rica',
        ]);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo_evento' => 'Modificacion',
        ]);
    }

    public function test_guardar_compra_dos_veces_actualiza_el_mismo_registro(): void
    {
        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'Proveedor' => 'Proveedor inicial',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'Proveedor' => 'Proveedor actualizado',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('COMPRAS', 1);
        $this->assertDatabaseHas('COMPRAS', [
            'fk_activo' => $this->activo->id,
            'Proveedor' => 'Proveedor actualizado',
        ]);
    }

    public function test_compra_rechaza_costo_negativo(): void
    {
        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'Costo_compra' => '-5',
        ])->assertSessionHasErrors('Costo_compra');

        $this->assertDatabaseCount('COMPRAS', 0);
    }

    public function test_auditor_no_puede_editar_compra(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)
            ->put("/activos/{$this->activo->id}/compra", ['Proveedor' => 'X'])
            ->assertForbidden();
    }

    public function test_admin_puede_adjuntar_imagen_al_activo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCMP0002',
            'Etiqueta_activo' => 'TBCMP0002',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'imagen' => UploadedFile::fake()->create('equipo.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $activo = Activo::where('Etiqueta_activo', 'TBCMP0002')->firstOrFail();

        $this->assertNotNull($activo->Imagen_Activo);
        $this->assertTrue(Storage::disk('local')->exists($activo->Imagen_Activo));
        $this->assertEmpty(Storage::disk('public')->files('uploads/activos'));
    }

    public function test_imagen_invalida_es_rechazada(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCMP0002',
            'Etiqueta_activo' => 'TBCMP0002',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'imagen' => UploadedFile::fake()->create('malicioso.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('imagen');

        $this->assertDatabaseMissing('ACTIVOS', ['Etiqueta_activo' => 'TBCMP0002']);
    }

    public function test_actualizar_imagen_reemplaza_la_anterior(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCMP0003',
            'Etiqueta_activo' => 'TBCMP0003',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'imagen' => UploadedFile::fake()->create('primera.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $activo = Activo::where('Etiqueta_activo', 'TBCMP0003')->firstOrFail();
        $anterior = $activo->Imagen_Activo;

        $this->actingAs($this->admin)->put("/activos/{$activo->id}", [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCMP0003',
            'Etiqueta_activo' => 'TBCMP0003',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'imagen' => UploadedFile::fake()->create('segunda.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $activo->refresh();
        $this->assertNotSame($anterior, $activo->Imagen_Activo);
        $this->assertFalse(Storage::disk('local')->exists($anterior));
        $this->assertTrue(Storage::disk('local')->exists($activo->Imagen_Activo));
    }

    public function test_usuario_autenticado_puede_ver_la_imagen(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post('/activos', [
            'Nombre_de_activo' => 'Laptop Dell Latitude TBCMP0004',
            'Etiqueta_activo' => 'TBCMP0004',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'Latitude',
            'Estado' => 'Disponible',
            'imagen' => UploadedFile::fake()->create('equipo.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $activo = Activo::where('Etiqueta_activo', 'TBCMP0004')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('activos.imagen', $activo))
            ->assertOk();
    }

    public function test_imagen_requiere_sesion(): void
    {
        $this->get(route('activos.imagen', $this->activo))
            ->assertRedirect(route('login'));
    }

    public function test_activo_sin_imagen_devuelve_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('activos.imagen', $this->activo))
            ->assertNotFound();
    }

    public function test_detalle_muestra_datos_de_compra(): void
    {
        Compra::create([
            'fk_activo' => $this->activo->id,
            'Proveedor' => 'Dell Costa Rica',
            'Numero_factura' => 'FAC-777',
        ]);

        $this->actingAs($this->admin)->get("/activos/{$this->activo->id}")
            ->assertOk()
            ->assertSee('Dell Costa Rica')
            ->assertSee('FAC-777')
            ->assertSee('datos de compra');
    }

    public function test_admin_puede_adjuntar_foto_de_factura(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'Numero_factura' => 'FAC-888',
            'imagen_factura' => UploadedFile::fake()->create('factura.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $compra = $this->activo->compra()->firstOrFail();

        $this->assertNotNull($compra->Imagen_Factura);
        $this->assertTrue(Storage::disk('local')->exists($compra->Imagen_Factura));
        $this->assertStringStartsWith('uploads/facturas/', $compra->Imagen_Factura);
    }

    public function test_actualizar_foto_de_factura_reemplaza_la_anterior(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'imagen_factura' => UploadedFile::fake()->create('primera.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $anterior = $this->activo->compra()->firstOrFail()->Imagen_Factura;

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'imagen_factura' => UploadedFile::fake()->create('segunda.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $compra = $this->activo->compra()->firstOrFail();

        $this->assertNotSame($anterior, $compra->Imagen_Factura);
        $this->assertFalse(Storage::disk('local')->exists($anterior));
        $this->assertTrue(Storage::disk('local')->exists($compra->Imagen_Factura));
    }

    public function test_foto_de_factura_invalida_es_rechazada(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'imagen_factura' => UploadedFile::fake()->create('factura.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('imagen_factura');

        $this->assertDatabaseCount('COMPRAS', 0);
    }

    public function test_usuario_autenticado_puede_ver_la_foto_de_factura(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'imagen_factura' => UploadedFile::fake()->create('factura.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('activos.factura', $this->activo))
            ->assertOk();
    }

    public function test_foto_de_factura_requiere_sesion(): void
    {
        $this->get(route('activos.factura', $this->activo))
            ->assertRedirect(route('login'));
    }

    public function test_factura_sin_imagen_devuelve_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('activos.factura', $this->activo))
            ->assertNotFound();
    }

    public function test_detalle_muestra_la_foto_de_factura(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->put("/activos/{$this->activo->id}/compra", [
            'imagen_factura' => UploadedFile::fake()->create('factura.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get("/activos/{$this->activo->id}")
            ->assertOk()
            ->assertSee(route('activos.factura', $this->activo));
    }
}
