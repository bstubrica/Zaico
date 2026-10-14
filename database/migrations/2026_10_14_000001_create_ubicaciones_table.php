<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de ubicaciones físicas de los activos. Sustituye el texto libre
     * que antes vivía en `ACTIVOS.Ubicacion`, sin eliminarlo (se conserva
     * denormalizado para Power BI).
     */
    public function up(): void
    {
        Schema::create('UBICACIONES', function (Blueprint $table) {
            $table->id();
            $table->string('Nombre', 150)->unique();
            $table->text('Descripcion')->nullable();
            $table->string('Estado', 20)->default('Activo');
            $table->timestampTz('Creado_el')->default(now());
            $table->timestampTz('Actualizado_el')->default(now());

            $table->index('Estado', 'idx_ubicaciones_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UBICACIONES');
    }
};
