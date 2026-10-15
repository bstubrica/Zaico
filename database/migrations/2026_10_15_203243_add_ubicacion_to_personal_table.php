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
        // En SQLite no se pueden añadir claves foráneas a una tabla existente
        // sin reconstruirla, y eso rompería la vista `vw_inventario`.
        // Las columnas e índices se añaden incrementalmente; la FK se aplica
        // en motores que la soportan (PostgreSQL).
        Schema::table('PERSONAL', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_ubicacion')->nullable()->after('Cargo');
            $table->index('fk_ubicacion', 'idx_personal_ubicacion');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('PERSONAL', function (Blueprint $table) {
                $table->foreign('fk_ubicacion')->references('id')->on('UBICACIONES')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('PERSONAL', function (Blueprint $table) {
                $table->dropForeign(['fk_ubicacion']);
            });
        }

        Schema::table('PERSONAL', function (Blueprint $table) {
            $table->dropIndex('idx_personal_ubicacion');
            $table->dropColumn('fk_ubicacion');
        });
    }
};
