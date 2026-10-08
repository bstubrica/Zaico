<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PERSONAL', function (Blueprint $table) {
            $table->id();
            $table->string('Nombre', 100);
            $table->string('Apellido', 100);
            $table->string('Nombre_usuario', 50)->unique();
            $table->string('Cargo', 100)->nullable();
            $table->text('Observaciones')->nullable();
            $table->string('Estado', 20)->default('Activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PERSONAL');
    }
};
