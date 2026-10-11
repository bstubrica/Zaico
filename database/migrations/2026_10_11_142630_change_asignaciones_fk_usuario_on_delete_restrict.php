<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('ASIGNACIONES', function (Blueprint $table) {
            $table->dropForeign(['fk_usuario']);
            $table->foreign('fk_usuario')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ASIGNACIONES', function (Blueprint $table) {
            $table->dropForeign(['fk_usuario']);
            $table->foreign('fk_usuario')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
