<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Compra;
use App\Models\EstadoActivo;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistaInventarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_vw_inventario_aplana_activo_estado_asignacion_y_compra(): void
    {
        $usuario = User::factory()->create(['role' => 'Admin']);

        $estado = EstadoActivo::create([
            'Nombre' => 'Asignado (deployed)',
            'Grupo' => 'Asignado',
            'Deployed' => true,
            'Deployable' => true,
            'Estado' => 1,
        ]);

        $activo = Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBSM1001',
            'Serial' => 'SN-1001',
            'Categoria' => 'Laptop',
            'Estado' => 'Asignado',
            'fk_estado' => $estado->id,
        ]);

        $persona = Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Pérez',
            'Nombre_usuario' => 'aperez',
            'Estado' => 'Activo',
        ]);

        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $usuario->id,
            'Fecha_asignacion' => '2026-01-15',
            'Estado' => 'Activa',
        ]);

        Compra::create([
            'fk_activo' => $activo->id,
            'Proveedor' => 'Dell',
            'Fecha_Compra' => '2025-06-01',
            'Costo_compra' => 1500.50,
            'Tiempo_garantia' => '36 meses',
        ]);

        $fila = DB::table('vw_inventario')->where('Etiqueta_activo', 'TBSM1001')->first();

        $this->assertNotNull($fila);
        $this->assertSame('Laptop Dell', $fila->Nombre_de_activo);
        $this->assertSame('Asignado', $fila->Estado);
        $this->assertSame('Asignado (deployed)', $fila->Estado_nombre);
        $this->assertSame('Asignado', $fila->Estado_grupo);
        $this->assertTrue((bool) $fila->Deployed);
        $this->assertSame('aperez', $fila->Asignado_usuario);
        $this->assertSame('Ana Pérez', $fila->Asignado_a);
        $this->assertSame('Dell', $fila->Proveedor);
        $this->assertSame('36 meses', $fila->Tiempo_garantia);
    }

    public function test_vw_inventario_incluye_activos_sin_asignacion_ni_compra(): void
    {
        $activo = Activo::create([
            'Nombre_de_activo' => 'Monitor',
            'Etiqueta_activo' => 'TBSM2002',
            'Estado' => 'Disponible',
        ]);

        $fila = DB::table('vw_inventario')->where('Etiqueta_activo', 'TBSM2002')->first();

        $this->assertNotNull($fila);
        $this->assertSame($activo->id, (int) $fila->id);
        $this->assertNull($fila->Asignado_a);
        $this->assertNull($fila->Asignado_usuario);
        $this->assertNull($fila->Proveedor);
    }
}
