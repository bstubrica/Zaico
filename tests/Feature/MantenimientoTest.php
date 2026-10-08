<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MantenimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Activo $activo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBMNT0001',
            'Estado' => 'Disponible',
        ]);
    }

    public function test_admin_puede_registrar_mantenimiento_con_evidencia(): void
    {
        Storage::fake('public');

        $respuesta = $this->actingAs($this->admin)->post(
            "/activos/{$this->activo->id}/mantenimientos",
            [
                'Tipo' => 'Preventivo',
                'Descripcion' => 'Limpieza interna y cambio de pasta térmica',
                'Fecha_mantenimiento' => '2026-10-08',
                'evidencias' => [UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf')],
            ]
        );

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('MANTENIMIENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo' => 'Preventivo',
        ]);
        $this->assertDatabaseHas('EVIDENCIAS_MANTENIMIENTO', ['Nombre_original' => 'reporte.pdf']);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo_evento' => 'Mantenimiento',
        ]);
    }

    public function test_archivo_invalido_es_rechazado(): void
    {
        Storage::fake('public');

        $respuesta = $this->actingAs($this->admin)->post(
            "/activos/{$this->activo->id}/mantenimientos",
            [
                'Tipo' => 'Preventivo',
                'Descripcion' => 'Prueba',
                'Fecha_mantenimiento' => '2026-10-08',
                'evidencias' => [UploadedFile::fake()->create('malicioso.exe', 10, 'application/x-msdownload')],
            ]
        );

        $respuesta->assertSessionHasErrors('evidencias.0');
        $this->assertDatabaseCount('MANTENIMIENTOS', 0);
    }

    public function test_auditor_no_puede_registrar_mantenimiento(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)
            ->post("/activos/{$this->activo->id}/mantenimientos", [
                'Tipo' => 'Correctivo',
                'Descripcion' => 'x',
                'Fecha_mantenimiento' => '2026-10-08',
            ])
            ->assertForbidden();
    }

    public function test_listado_global_requiere_sesion(): void
    {
        $this->get('/mantenimientos')->assertRedirect(route('login'));
    }
}
