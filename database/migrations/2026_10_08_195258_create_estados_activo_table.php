<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ESTADOS_ACTIVO', function (Blueprint $table) {
            $table->id();
            $table->string('Nombre', 60)->unique();
            $table->string('Grupo', 30);
            $table->boolean('Deployed')->default(false);
            $table->boolean('Deployable')->default(false);
            $table->smallInteger('Estado')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ESTADOS_ACTIVO');
    }
};
