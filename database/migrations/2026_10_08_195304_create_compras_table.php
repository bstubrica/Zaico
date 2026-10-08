<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('COMPRAS', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_activo')->unique();
            $table->string('Numero_Requisicion', 50)->nullable();
            $table->date('Fecha_Compra')->nullable();
            $table->string('Proveedor', 150)->nullable();
            $table->decimal('Costo_compra', 12, 2)->nullable();
            $table->string('Tiempo_garantia', 50)->nullable();
            $table->string('Imagen_Factura', 255)->nullable();
            $table->string('Garantia', 50)->nullable();
            $table->string('Numero_factura', 50)->nullable();

            $table->foreign('fk_activo')->references('id')->on('ACTIVOS')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('COMPRAS');
    }
};
