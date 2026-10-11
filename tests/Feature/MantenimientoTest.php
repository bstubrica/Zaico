<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\EvidenciaMantenimiento;
use App\Models\Mantenimiento;
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

    public function test_admin_puede_editar_mantenimiento(): void
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

        $mantenimiento = Mantenimiento::firstOrFail();
        $evidenciaId = $mantenimiento->evidencias()->firstOrFail()->id;

        $respuesta = $this->actingAs($this->admin)->put(
            "/mantenimientos/{$mantenimiento->id}",
            [
                'Tipo' => 'Correctivo',
                'Descripcion' => 'Descripcion corregida',
                'Fecha_mantenimiento' => '2026-10-09',
                'evidencias_eliminar' => [$evidenciaId],
                'evidencias' => [UploadedFile::fake()->create('nuevo.pdf', 100, 'application/pdf')],
            ]
        );

        $respuesta->assertSessionHasNoErrors()->assertRedirect(route('activos.show', $this->activo->id));

        $this->assertDatabaseHas('MANTENIMIENTOS', [
            'id' => $mantenimiento->id,
            'Tipo' => 'Correctivo',
            'Descripcion' => 'Descripcion corregida',
            'Fecha_mantenimiento' => '2026-10-09',
        ]);
        $this->assertDatabaseMissing('EVIDENCIAS_MANTENIMIENTO', ['id' => $evidenciaId]);
        $this->assertDatabaseHas('EVIDENCIAS_MANTENIMIENTO', ['Nombre_original' => 'nuevo.pdf']);
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo_evento' => 'Mantenimiento',
            'Descripcion' => 'Mantenimiento Correctivo actualizado: Descripcion corregida',
        ]);
    }

    public function test_pagina_de_edicion_muestra_el_mantenimiento(): void
    {
        $this->actingAs($this->admin)->post(
            "/activos/{$this->activo->id}/mantenimientos",
            [
                'Tipo' => 'Preventivo',
                'Descripcion' => 'Limpieza interna',
                'Fecha_mantenimiento' => '2026-10-08',
            ]
        )->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('mantenimientos.edit', Mantenimiento::firstOrFail()))
            ->assertOk()
            ->assertSee('Limpieza interna')
            ->assertSee(route('mantenimientos.update', Mantenimiento::firstOrFail()));
    }

    public function test_admin_puede_eliminar_mantenimiento_y_sus_evidencias_del_disco(): void
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

        $mantenimiento = Mantenimiento::firstOrFail();

        $respuesta = $this->actingAs($this->admin)
            ->delete("/mantenimientos/{$mantenimiento->id}");

        $respuesta->assertSessionHasNoErrors()
            ->assertRedirect(route('activos.show', $this->activo->id));

        $this->assertDatabaseCount('MANTENIMIENTOS', 0);
        $this->assertDatabaseCount('EVIDENCIAS_MANTENIMIENTO', 0);
        $this->assertEmpty(Storage::disk('local')->files('uploads/mantenimientos'));
        $this->assertDatabaseHas('HISTORIAL_EVENTOS', [
            'fk_activo' => $this->activo->id,
            'Tipo_evento' => 'Mantenimiento',
        ]);
    }

    public function test_auditor_no_puede_editar_ni_eliminar_mantenimiento(): void
    {
        $this->actingAs($this->admin)->post(
            "/activos/{$this->activo->id}/mantenimientos",
            [
                'Tipo' => 'Preventivo',
                'Descripcion' => 'Limpieza interna',
                'Fecha_mantenimiento' => '2026-10-08',
            ]
        )->assertSessionHasNoErrors();

        $auditor = User::factory()->create(['role' => 'Auditor']);
        $mantenimiento = Mantenimiento::firstOrFail();

        $this->actingAs($auditor)
            ->get("/mantenimientos/{$mantenimiento->id}/editar")
            ->assertForbidden();
        $this->actingAs($auditor)
            ->put("/mantenimientos/{$mantenimiento->id}", [
                'Tipo' => 'Correctivo',
                'Descripcion' => 'x',
                'Fecha_mantenimiento' => '2026-10-08',
            ])
            ->assertForbidden();
        $this->actingAs($auditor)
            ->delete("/mantenimientos/{$mantenimiento->id}")
            ->assertForbidden();
        $this->assertDatabaseCount('MANTENIMIENTOS', 1);
    }

    public function test_editar_mantenimiento_requiere_sesion(): void
    {
        $this->get('/mantenimientos/1/editar')->assertRedirect(route('login'));
        $this->put('/mantenimientos/1')->assertRedirect(route('login'));
        $this->delete('/mantenimientos/1')->assertRedirect(route('login'));
    }
}
