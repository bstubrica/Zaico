<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula `ACTIVOS` con los catálogos de ubicaciones y modelos. Los campos
     * de texto `Ubicacion` y `Modelo` se conservan (denormalizados) para no
     * romper reportes ni Power BI; además se rellenan los catálogos con los
     * valores ya existentes en el inventario y se enlazan las filas.
     */
    public function up(): void
    {
        // En SQLite no se pueden añadir claves foráneas a una tabla existente
        // sin reconstruirla, y eso rompería la vista `vw_inventario`. Las
        // columnas y sus índices sí se añaden de forma incremental; las FK se
        // aplican solo en motores que las soportan (PostgreSQL).
        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_ubicacion')->nullable();
            $table->unsignedBigInteger('fk_modelo')->nullable();
        });

        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->index('fk_ubicacion', 'idx_activos_ubicacion');
            $table->index('fk_modelo', 'idx_activos_modelo');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('ACTIVOS', function (Blueprint $table) {
                $table->foreign('fk_ubicacion')->references('id')->on('UBICACIONES')->nullOnDelete();
                $table->foreign('fk_modelo')->references('id')->on('MODELOS_ACTIVO')->nullOnDelete();
            });
        }

        $this->sembrarUbicaciones();
        $this->sembrarModelos();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('ACTIVOS', function (Blueprint $table) {
                $table->dropForeign(['fk_ubicacion']);
                $table->dropForeign(['fk_modelo']);
            });
        }

        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->dropIndex('idx_activos_ubicacion');
            $table->dropIndex('idx_activos_modelo');
            $table->dropColumn(['fk_ubicacion', 'fk_modelo']);
        });
    }

    private function sembrarUbicaciones(): void
    {
        $ahora = now();

        $nombres = DB::table('ACTIVOS')
            ->whereNotNull('Ubicacion')
            ->where('Ubicacion', '<>', '')
            ->distinct()
            ->pluck('Ubicacion');

        foreach ($nombres as $crudo) {
            $nombre = trim((string) $crudo);

            if ($nombre === '' || strcasecmp($nombre, 'N/A') === 0) {
                continue;
            }

            DB::table('UBICACIONES')->insertOrIgnore([
                'Nombre' => mb_substr($nombre, 0, 150),
                'Estado' => 'Activo',
                'Creado_el' => $ahora,
                'Actualizado_el' => $ahora,
            ]);
        }

        DB::table('ACTIVOS')->whereNotNull('Ubicacion')->update([
            'fk_ubicacion' => DB::raw('(SELECT "id" FROM "UBICACIONES" WHERE "UBICACIONES"."Nombre" = "ACTIVOS"."Ubicacion")'),
        ]);
    }

    private function sembrarModelos(): void
    {
        $ahora = now();

        $modelos = DB::table('ACTIVOS')
            ->whereNotNull('Modelo')
            ->where('Modelo', '<>', '')
            ->distinct()
            ->get(['Modelo', 'Fabricante', 'Categoria']);

        foreach ($modelos as $modelo) {
            $nombre = trim((string) $modelo->Modelo);

            if ($nombre === '') {
                continue;
            }

            DB::table('MODELOS_ACTIVO')->insertOrIgnore([
                'Nombre' => mb_substr($nombre, 0, 100),
                'Fabricante' => $this->textoOpcional($modelo->Fabricante, 100),
                'Categoria' => $this->textoOpcional($modelo->Categoria, 100),
                'Estado' => 'Activo',
                'Creado_el' => $ahora,
                'Actualizado_el' => $ahora,
            ]);
        }

        DB::table('ACTIVOS')->whereNotNull('Modelo')->update([
            'fk_modelo' => DB::raw('(SELECT "id" FROM "MODELOS_ACTIVO" WHERE "MODELOS_ACTIVO"."Nombre" = "ACTIVOS"."Modelo")'),
        ]);
    }

    private function textoOpcional(?string $valor, int $largo): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : mb_substr($texto, 0, $largo);
    }
};
