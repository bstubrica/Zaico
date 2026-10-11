<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ALERTAS', function (Blueprint $table) {
            $table->id();
            $table->string('Tipo', 30);
            $table->unsignedBigInteger('fk_activo');
            $table->string('Mensaje', 255);
            $table->date('Fecha_referencia')->nullable();
            $table->string('Estado', 20)->default('Activa');
            $table->timestamps();

            $table->unique(['Tipo', 'fk_activo'], 'uniq_alertas_tipo_activo');
            $table->index('Estado', 'idx_alertas_estado');
            $table->foreign('fk_activo')->references('id')->on('ACTIVOS')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ALERTAS');
    }
};
