<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('DESINCORPORACIONES', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fk_activo');
            $table->string('Tipo', 20);
            $table->string('Destinatario', 150);
            $table->string('Detalle_destino', 255)->nullable();
            $table->string('Referencia', 100)->nullable();
            $table->decimal('Monto', 12, 2)->nullable();
            $table->string('Moneda', 3)->nullable();
            $table->text('Motivo');
            $table->date('Fecha');
            $table->string('Estado', 20)->default('Vigente');
            $table->unsignedBigInteger('fk_usuario');
            $table->timestampTz('Creado_el')->default(now());

            $table->index('fk_activo', 'idx_desinc_activo');
            $table->index('Tipo', 'idx_desinc_tipo');
            $table->index('Fecha', 'idx_desinc_fecha');
            $table->foreign('fk_activo')->references('id')->on('ACTIVOS')->restrictOnDelete();
            $table->foreign('fk_usuario')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('DESINCORPORACIONES');
    }
};
