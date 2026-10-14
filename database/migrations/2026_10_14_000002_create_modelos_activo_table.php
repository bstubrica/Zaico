<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de modelos de activos. Sustituye el texto libre que antes vivía
     * en `ACTIVOS.Modelo`, sin eliminarlo (se conserva denormalizado para
     * Power BI).
     */
    public function up(): void
    {
        Schema::create('MODELOS_ACTIVO', function (Blueprint $table) {
            $table->id();
            $table->string('Nombre', 100)->unique();
            $table->string('Fabricante', 100)->nullable();
            $table->string('Categoria', 100)->nullable();
            $table->string('Estado', 20)->default('Activo');
            $table->timestampTz('Creado_el')->default(now());
            $table->timestampTz('Actualizado_el')->default(now());

            $table->index('Estado', 'idx_modelos_estado');
            $table->index('Categoria', 'idx_modelos_categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MODELOS_ACTIVO');
    }
};
