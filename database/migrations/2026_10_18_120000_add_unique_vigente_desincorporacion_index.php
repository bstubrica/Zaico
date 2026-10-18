<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Un activo no puede tener dos desincorporaciones vigentes a la vez. La
     * regla se validaba solo en la aplicación (chequeo + insert), lo que
     * permitía que dos solicitudes concurrentes dejaran una doble baja vigente.
     * El índice único parcial la garantiza en la base de datos, igual que
     * `asignaciones_activa_unica` para las asignaciones activas.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX desinc_vigente_unica ON "DESINCORPORACIONES" ("fk_activo") WHERE "Estado" = \'Vigente\''
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS desinc_vigente_unica');
    }
};
