<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ASIGNACIONES', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_Activo');
            $table->unsignedBigInteger('fk_Personal');
            $table->unsignedBigInteger('fk_usuario');
            $table->date('Fecha_asignacion');
            $table->date('Fecha_devolucion')->nullable();
            $table->text('Observaciones')->nullable();
            $table->string('Estado', 20)->default('Activa');

            $table->index('fk_Activo', 'idx_asignaciones_activo');
            $table->index('fk_Personal', 'idx_asignaciones_personal');
            $table->foreign('fk_Activo')->references('id')->on('ACTIVOS')->restrictOnDelete();
            $table->foreign('fk_Personal')->references('id')->on('PERSONAL')->restrictOnDelete();
            $table->foreign('fk_usuario')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ASIGNACIONES');
    }
};
