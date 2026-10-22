<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índice GIN `pg_trgm` sobre `ACTIVOS."IP"`.
 *
 * El filtro por IP del listado (`BusquedaAproximada`) busca con `%término%`,
 * así que en PostgreSQL el `ilike` con comodín al inicio no aprovecha el
 * índice B-tree `idx_activos_ip` y termina recorriendo la tabla. Con la IP
 * como dato general del equipo (no solo de los servidores) el filtro gana
 * peso, así que se cubre con el mismo patrón trigram que ya usan
 * `Nombre_de_activo`, `Serial`, `Etiqueta_activo` y `Modelo`.
 *
 * SQLite no soporta extensiones: los tests corren en memoria y deben seguir
 * pasando sin este bloque.
 */
return new class extends Migration
{
    private const INDICE = 'idx_activos_ip_trgm';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // pg_trgm es una extensión "trusted" desde PostgreSQL 13: basta con
        // privilegio de propietario de la base (el usuario de la aplicación).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX '.self::INDICE.' ON "ACTIVOS" USING gin ("IP" gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS '.self::INDICE);
        }
    }
};
