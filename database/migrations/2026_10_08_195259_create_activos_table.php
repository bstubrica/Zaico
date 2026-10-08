<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ACTIVOS', function (Blueprint $table) {
            $table->id();
            $table->string('Nombre_de_activo', 150);
            $table->string('Ubicacion', 150)->nullable();
            $table->string('Ubicacion_Predeterminada', 150)->nullable();
            $table->string('Serial', 100)->unique()->nullable();
            $table->string('Fabricante', 100)->nullable();
            $table->string('Categoria', 100)->nullable();
            $table->string('Modelo', 100)->nullable();
            $table->text('Observaciones')->nullable();
            $table->string('Etiqueta_activo', 100)->unique();
            $table->string('Estado', 20)->default('Activo');
            $table->string('Direccion_MAC', 17)->nullable();
            $table->string('Imagen_Activo', 255)->nullable();
            $table->unsignedBigInteger('fk_estado')->nullable();
            $table->timestampTz('Creado_el')->default(now());
            $table->timestampTz('Actualizado_el')->default(now());

            $table->index('Nombre_de_activo', 'idx_activos_nombre');
            $table->index('Categoria', 'idx_activos_categoria');
            $table->index('fk_estado', 'idx_activos_estado');
            $table->foreign('fk_estado')->references('id')->on('ESTADOS_ACTIVO')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ACTIVOS');
    }
};
