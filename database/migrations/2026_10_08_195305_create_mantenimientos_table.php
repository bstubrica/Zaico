<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('MANTENIMIENTOS', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_activo');
            $table->string('Tipo', 30);
            $table->text('Descripcion');
            $table->date('Fecha_mantenimiento');
            $table->unsignedBigInteger('fk_usuario');
            $table->timestampTz('Creado_el')->default(now());

            $table->index('fk_activo', 'idx_mantenimientos_activo');
            $table->index('Fecha_mantenimiento', 'idx_mantenimientos_fecha');
            $table->foreign('fk_activo')->references('id')->on('ACTIVOS')->cascadeOnDelete();
            $table->foreign('fk_usuario')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MANTENIMIENTOS');
    }
};
