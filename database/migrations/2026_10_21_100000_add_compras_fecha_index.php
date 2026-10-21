<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice B-tree sobre la fecha de compra: la evolución mensual de
     * compras/costos del módulo de indicadores agrupa por esta columna y el
     * rango de fechas del filtro. Aplica a ambos motores (PostgreSQL en
     * producción, SQLite en desarrollo/tests).
     */
    public function up(): void
    {
        Schema::table('COMPRAS', function (Blueprint $table) {
            $table->index('Fecha_Compra', 'idx_compras_fecha_compra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('COMPRAS', function (Blueprint $table) {
            $table->dropIndex('idx_compras_fecha_compra');
        });
    }
};
