<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La columna denormalizada `ACTIVOS.Estado` (grupo de estado) se usa en los
     * filtros del listado, en el agrupamiento del panel y en la detección de
     * alertas; sin índice obliga a un escaneo secuencial de la tabla.
     */
    public function up(): void
    {
        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->index('Estado', 'idx_activos_estado_valor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->dropIndex('idx_activos_estado_valor');
        });
    }
};
