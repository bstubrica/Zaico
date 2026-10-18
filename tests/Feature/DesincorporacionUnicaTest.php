<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\User;
use App\Services\ActivoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El índice único parcial `desinc_vigente_unica` garantiza en la base de datos
 * que un activo no acumule dos bajas vigentes, cerrando la carrera que la sola
 * validación en la aplicación no cubría.
 */
class DesincorporacionUnicaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Activo $activo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->activo = Activo::create([
            'Nombre_de_activo' => 'Equipo de prueba',
            'Etiqueta_activo' => 'TBUNI001',
            'Estado' => 'Disponible',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function baja(array $extra = []): Desincorporacion
    {
        return Desincorporacion::create(array_merge([
            'fk_activo' => $this->activo->id,
            'Tipo' => 'Donacion',
            'Destinatario' => 'Escuela local',
            'Motivo' => 'Equipo en buen estado',
            'Fecha' => now()->toDateString(),
            'Estado' => 'Vigente',
            'fk_usuario' => $this->admin->id,
        ], $extra));
    }

    public function test_la_base_rechaza_una_segunda_baja_vigente(): void
    {
        $this->baja();

        $this->expectException(QueryException::class);

        $this->baja();
    }

    public function test_una_baja_anulada_no_bloquea_una_nueva_vigente(): void
    {
        $this->baja(['Estado' => 'Anulada']);
        $this->baja();

        $this->assertSame(2, Desincorporacion::where('fk_activo', $this->activo->id)->count());
        $this->assertSame(1, Desincorporacion::where('fk_activo', $this->activo->id)->where('Estado', 'Vigente')->count());
    }

    public function test_la_baja_repetida_en_la_aplicacion_avisa_en_espanol(): void
    {
        $disponible = EstadoActivo::create([
            'Nombre' => 'Disponible (deployable)',
            'Grupo' => 'Disponible',
            'Deployed' => false,
            'Deployable' => true,
            'Estado' => 1,
        ]);
        EstadoActivo::create([
            'Nombre' => 'Desincorporado (undeployable)',
            'Grupo' => 'Desincorporado',
            'Deployed' => true,
            'Deployable' => false,
            'Estado' => 1,
        ]);
        $this->activo->update(['fk_estado' => $disponible->id]);

        $this->baja();

        $this->expectExceptionMessage('El activo ya está desincorporado.');

        app(ActivoService::class)->desincorporar($this->activo->fresh(), [
            'tipo' => 'Donacion',
            'destinatario' => 'Escuela local',
            'motivo' => 'Repetida',
            'fecha' => now()->toDateString(),
        ], $this->admin->id);
    }
}
