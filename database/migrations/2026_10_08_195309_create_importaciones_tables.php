<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('IMPORTACIONES_LOG', function (Blueprint $table) {
            $table->id();
            $table->string('Nombre_archivo', 255);
            $table->unsignedBigInteger('fk_usuario');
            $table->timestampTz('Fecha')->default(now());
            $table->integer('Total_filas')->default(0);
            $table->integer('Insertadas')->default(0);
            $table->integer('Actualizadas')->default(0);
            $table->integer('Errores')->default(0);
            $table->string('Modo', 20)->default('Commit');
            $table->string('Estado', 20)->default('EnProceso');

            $table->foreign('fk_usuario')->references('id')->on('users');
        });

        Schema::create('IMPORTACIONES_DETALLE', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_importacion');
            $table->integer('Fila_numero');
            $table->string('Etiqueta', 100)->nullable();
            $table->string('Accion', 20);
            $table->text('Mensaje')->nullable();
            $table->jsonb('Datos_previos')->nullable();

            $table->index('fk_importacion', 'idx_importaciones_detalle_log');
            $table->foreign('fk_importacion')->references('id')->on('IMPORTACIONES_LOG')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('IMPORTACIONES_DETALLE');
        Schema::dropIfExists('IMPORTACIONES_LOG');
    }
};
