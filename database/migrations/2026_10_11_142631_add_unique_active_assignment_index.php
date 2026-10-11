<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Un activo no puede tener dos asignaciones activas a la vez. La regla se
     * validaba solo en la aplicación (chequeo + insert), lo que permitía que dos
     * solicitudes concurrentes crearan asignaciones activas duplicadas. El índice
     * único parcial la garantiza en la base de datos.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX asignaciones_activa_unica ON "ASIGNACIONES" ("fk_Activo") WHERE "Estado" = \'Activa\''
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asignaciones_activa_unica');
    }
};
