<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El usuario que registró una asignación no debe poder borrarse sin más:
     * el `cascadeOnDelete` destruía silenciosamente el historial de asignaciones
     * (y con él la trazabilidad del inventario). Se cambia a `restrictOnDelete`.
     */
    public function up(): void
    {
        $this->recreateForeign('restrict');
    }

    public function down(): void
    {
        $this->recreateForeign('cascade');
    }

    /**
     * SQLite no soporta `DROP FOREIGN KEY`: Laravel reconstruye la tabla
     * (crea __temp__, copia, elimina y renombra). El renombrado falla si una
     * vista (`vw_inventario`) referencia la tabla, porque SQLite valida las
     * referencias. `legacy_alter_table` desactiva esa validación durante el
     * proceso; en PostgreSQL no aplica y el cambio es un ALTER directo.
     */
    private function recreateForeign(string $onDelete): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            DB::statement('PRAGMA legacy_alter_table = ON');
        }

        try {
            Schema::table('ASIGNACIONES', function (Blueprint $table) use ($onDelete) {
                $table->dropForeign(['fk_usuario']);
                $table->foreign('fk_usuario')->references('id')->on('users')->onDelete($onDelete);
            });
        } finally {
            if ($isSqlite) {
                DB::statement('PRAGMA legacy_alter_table = OFF');
            }
        }
    }
};
