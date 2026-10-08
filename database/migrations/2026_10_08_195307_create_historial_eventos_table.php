<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('HISTORIAL_EVENTOS', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fk_activo');
            $table->string('Tipo_evento', 40);
            $table->text('Descripcion')->nullable();
            $table->text('Valor_anterior')->nullable();
            $table->text('Valor_nuevo')->nullable();
            $table->unsignedBigInteger('fk_usuario')->nullable();
            $table->timestampTz('Fecha')->default(now());

            $table->index('fk_activo', 'idx_historial_activo');
            $table->index('Fecha', 'idx_historial_fecha');
            $table->foreign('fk_activo')->references('id')->on('ACTIVOS')->cascadeOnDelete();
            $table->foreign('fk_usuario')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('HISTORIAL_EVENTOS');
    }
};
