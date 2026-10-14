<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\ImportacionLog;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokePantallasTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_pantallas_renderizan(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $estado = EstadoActivo::create([
            'Nombre' => 'Disponible (deployable)',
            'Grupo' => 'Disponible',
            'Deployed' => false,
            'Deployable' => true,
            'Estado' => 1,
        ]);

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBSMOKE01',
            'Categoria' => 'Laptop',
            'Fabricante' => 'Dell',
            'Modelo' => 'XPS',
            'Ubicacion' => 'Oficina',
            'Estado' => 'Disponible',
            'fk_estado' => $estado->id,
        ]);

        $persona = Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Pérez',
            'Nombre_usuario' => 'aperez',
            'Cargo' => 'Analista',
            'Estado' => 'Activo',
        ]);

        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $admin->id,
            'Fecha_asignacion' => '2026-10-01',
            'Estado' => 'Activa',
        ]);

        Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Limpieza',
            'Fecha_mantenimiento' => '2026-10-02',
            'fk_usuario' => $admin->id,
        ]);

        $importacion = ImportacionLog::create([
            'Nombre_archivo' => 'custom-assets-report.csv',
            'fk_usuario' => $admin->id,
            'Total_filas' => 3,
            'Insertadas' => 2,
            'Actualizadas' => 1,
            'Errores' => 0,
            'Modo' => 'DryRun',
            'Estado' => 'Completado',
        ]);

        $rutas = [
            route('activos.create'),
            route('activos.show', $activo),
            route('activos.edit', $activo),
            route('personal.index'),
            route('personal.create'),
            route('personal.show', $persona),
            route('personal.edit', $persona),
            route('mantenimientos.index'),
            route('activos.mantenimientos', $activo),
            route('historial.index'),
            route('estados.index'),
            route('estados.create'),
            route('estados.edit', $estado),
            route('ubicaciones.index'),
            route('ubicaciones.create'),
            route('modelos.index'),
            route('modelos.create'),
            route('importaciones.index'),
            route('importaciones.show', $importacion),
            route('alertas.index'),
            route('activos.buscar'),
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($admin)->get($ruta)->assertOk();
        }
    }
}
