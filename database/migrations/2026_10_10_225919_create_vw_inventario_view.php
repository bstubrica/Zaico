<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista de solo lectura para consumo directo de Power BI (Fase 9).
 *
 * Aplana el inventario: datos del activo + estado + asignación activa +
 * persona asignada + compra/garantía, en una sola "tabla" consultable.
 *
 * Los alias van entre comillas para preservar el casing exacto de las
 * columnas (compatibilidad con el esquema histórico y Power BI).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW "vw_inventario" AS
            SELECT
                a."id"                          AS "id",
                a."Etiqueta_activo"             AS "Etiqueta_activo",
                a."Nombre_de_activo"            AS "Nombre_de_activo",
                a."Serial"                      AS "Serial",
                a."Fabricante"                  AS "Fabricante",
                a."Categoria"                   AS "Categoria",
                a."Modelo"                      AS "Modelo",
                a."Ubicacion"                   AS "Ubicacion",
                a."Ubicacion_Predeterminada"    AS "Ubicacion_Predeterminada",
                a."Estado"                      AS "Estado",
                e."Nombre"                      AS "Estado_nombre",
                e."Grupo"                       AS "Estado_grupo",
                e."Deployed"                    AS "Deployed",
                e."Deployable"                  AS "Deployable",
                p."Nombre_usuario"              AS "Asignado_usuario",
                CASE WHEN p."id" IS NULL THEN NULL
                     ELSE trim(p."Nombre" || ' ' || p."Apellido") END AS "Asignado_a",
                asig."Fecha_asignacion"         AS "Fecha_asignacion",
                c."Proveedor"                   AS "Proveedor",
                c."Fecha_Compra"                AS "Fecha_Compra",
                c."Costo_compra"                AS "Costo_compra",
                c."Tiempo_garantia"             AS "Tiempo_garantia",
                c."Garantia"                    AS "Garantia",
                a."Creado_el"                   AS "Creado_el",
                a."Actualizado_el"              AS "Actualizado_el"
            FROM "ACTIVOS" a
            LEFT JOIN "ESTADOS_ACTIVO" e ON e."id" = a."fk_estado"
            LEFT JOIN "ASIGNACIONES" asig ON asig."fk_Activo" = a."id" AND asig."Estado" = 'Activa'
            LEFT JOIN "PERSONAL" p ON p."id" = asig."fk_Personal"
            LEFT JOIN "COMPRAS" c ON c."fk_activo" = a."id"
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS "vw_inventario"');
    }
};
