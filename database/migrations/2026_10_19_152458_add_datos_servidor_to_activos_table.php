<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos propios de los equipos de categoría «Servidor» (IP, sistema operativo,
 * memoria, disco y estatus encendido/apagado). Se guardan como columnas
 * nullable del propio activo para no afectar a los equipos de otras categorías
 * ni duplicar la relación 1:1 en una tabla aparte; las tarjetas de red, que son
 * varias por servidor, viven en `TARJETAS_RED`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->string('IP', 45)->nullable()->after('Direccion_MAC');
            $table->string('Sistema_Operativo', 100)->nullable()->after('IP');
            $table->string('Memoria_RAM', 100)->nullable()->after('Sistema_Operativo');
            $table->string('Capacidad_Disco', 100)->nullable()->after('Memoria_RAM');
            $table->string('Estatus_Servidor', 20)->nullable()->after('Capacidad_Disco');
        });

        // El listado permite filtrar por IP.
        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->index('IP', 'idx_activos_ip');
        });
    }

    public function down(): void
    {
        Schema::table('ACTIVOS', function (Blueprint $table) {
            $table->dropIndex('idx_activos_ip');
            $table->dropColumn([
                'IP',
                'Sistema_Operativo',
                'Memoria_RAM',
                'Capacidad_Disco',
                'Estatus_Servidor',
            ]);
        });
    }
};
