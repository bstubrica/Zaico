<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('EVIDENCIAS_MANTENIMIENTO', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_mantenimiento');
            $table->string('Ruta_archivo', 300);
            $table->string('Nombre_original', 255);
            $table->string('Tipo_mime', 100)->nullable();
            $table->unsignedBigInteger('Tamano_bytes')->nullable();
            $table->timestampTz('Subido_el')->default(now());

            $table->foreign('fk_mantenimiento')->references('id')->on('MANTENIMIENTOS')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('EVIDENCIAS_MANTENIMIENTO');
    }
};
