<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas de `ACTIVOS` buscadas con `%término%` (`BusquedaAproximada`).
     * En PostgreSQL el `ilike` con comodín al inicio no usa un índice B-tree,
     * así que sin un índice GIN `pg_trgm` cada búsqueda escanea la tabla.
     *
     * @var array<string, string>
     */
    private const COLUMNAS_TRGM = [
        'Nombre_de_activo' => 'idx_activos_nombre_trgm',
        'Serial' => 'idx_activos_serial_trgm',
        'Etiqueta_activo' => 'idx_activos_etiqueta_trgm',
        'Modelo' => 'idx_activos_modelo_trgm',
    ];

    /**
     * Índices de rendimiento (plan de optimización 2026-10):
     *
     * - `ASIGNACIONES.Fecha_asignacion` y `ACTIVOS.Creado_el`: ordenamientos
     *   y rangos de fechas que hoy obligan a recorrer la tabla completa.
     * - GIN `pg_trgm` (solo PostgreSQL) sobre las columnas del filtro de
     *   búsqueda de activos. SQLite no soporta extensiones: los tests corren
     *   en memoria y deben seguir pasando sin este bloque.
     *
     * `ALERTAS` no necesita índice nuevo: `uniq_alertas_tipo_activo`
     * (Tipo, fk_activo) ya es un índice compuesto con esas dos columnas.
     */
    public function up(): void
    {
        Schema::table('ASIGNACIONES', function (Blueprint $table) {
            $table->index('Fecha_asignacion', 'idx_asignaciones_fecha_asignacion');
        });

        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->index('Creado_el', 'idx_activos_creado_el');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // pg_trgm es una extensión "trusted" desde PostgreSQL 13: basta con
        // privilegio de propietario de la base (el usuario de la aplicación).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::COLUMNAS_TRGM as $columna => $indice) {
            DB::statement("CREATE INDEX {$indice} ON \"ACTIVOS\" USING gin (\"{$columna}\" gin_trgm_ops)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::COLUMNAS_TRGM as $indice) {
                DB::statement("DROP INDEX IF EXISTS {$indice}");
            }
        }

        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->dropIndex('idx_activos_creado_el');
        });

        Schema::table('ASIGNACIONES', function (Blueprint $table) {
            $table->dropIndex('idx_asignaciones_fecha_asignacion');
        });
    }
};
