<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\EvidenciaMantenimiento;
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
        Storage::fake('local');

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
        Storage::fake('local');

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

    public function test_la_evidencia_se_guarda_en_disco_privado(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->admin)->post(
            "/activos/{$this->activo->id}/mantenimientos",
            [
                'Tipo' => 'Preventivo',
                'Descripcion' => 'Limpieza interna',
                'Fecha_mantenimiento' => '2026-10-08',
                'evidencias' => [UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf')],
            ]
        )->assertSessionHasNoErrors();

        $this->assertNotEmpty(Storage::disk('local')->files('uploads/mantenimientos'));
        $this->assertEmpty(Storage::disk('public')->files('uploads/mantenimientos'));
    }

    public function test_usuario_autenticado_puede_descargar_evidencia(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post(
            "/activos/{$this->activo->id}/mantenimientos",
            [
                'Tipo' => 'Preventivo',
                'Descripcion' => 'Limpieza interna',
                'Fecha_mantenimiento' => '2026-10-08',
                'evidencias' => [UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf')],
            ]
        )->assertSessionHasNoErrors();

        $evidencia = EvidenciaMantenimiento::firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('mantenimientos.evidencia', $evidencia))
            ->assertOk()
            ->assertDownload('reporte.pdf');
    }

    public function test_descarga_de_evidencia_requiere_sesion(): void
    {
        $this->get('/mantenimientos/evidencias/'.$this->activo->id)
            ->assertRedirect(route('login'));
    }
}
