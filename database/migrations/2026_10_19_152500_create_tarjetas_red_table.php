<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarjetas de red de un equipo (normalmente un servidor). Relación 1:N con
 * `ACTIVOS` siguiendo el patrón de `MANTENIMIENTOS`: cada tarjeta conoce su
 * número de orden, la interfaz, su MAC/IP y el switch del inventario al que
 * está conectada (`fk_switch`, con el nombre denormalizado en `Switch` como en
 * el resto del esquema para Power BI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TARJETAS_RED', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_activo');
            $table->unsignedSmallInteger('Numero');
            $table->string('Nombre', 60)->nullable();
            $table->string('Direccion_MAC', 17)->nullable();
            $table->string('IP', 45)->nullable();
            $table->unsignedBigInteger('fk_switch')->nullable();
            $table->string('Switch', 150)->nullable();
            $table->string('Puerto', 30)->nullable();
            $table->timestampTz('Creado_el')->default(now());

            $table->index('fk_activo', 'idx_tarjetas_red_activo');
            $table->index('fk_switch', 'idx_tarjetas_red_switch');
            $table->unique(['fk_activo', 'Numero'], 'tarjetas_red_activo_numero_unica');
            $table->foreign('fk_activo')->references('id')->on('ACTIVOS')->cascadeOnDelete();
            $table->foreign('fk_switch')->references('id')->on('ACTIVOS')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TARJETAS_RED');
    }
};
