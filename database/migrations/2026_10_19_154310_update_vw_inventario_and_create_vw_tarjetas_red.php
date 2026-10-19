<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende las vistas de solo lectura para Power BI con los datos de servidor:
 * las columnas nuevas en `vw_inventario` (IP, sistema operativo, RAM, disco y
 * estatus) y la vista `vw_tarjetas_red`, que aplana las tarjetas de red con su
 * equipo y su switch.
 *
 * Se recrea la vista en lugar de reemplazarla: PostgreSQL no admite
 * `CREATE OR REPLACE VIEW` cuando cambia el conjunto o el tipo de columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS "vw_inventario"');

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
                a."IP"                          AS "IP",
                a."Sistema_Operativo"           AS "Sistema_Operativo",
                a."Memoria_RAM"                 AS "Memoria_RAM",
                a."Capacidad_Disco"             AS "Capacidad_Disco",
                a."Estatus_Servidor"            AS "Estatus_Servidor",
                a."Creado_el"                   AS "Creado_el",
                a."Actualizado_el"              AS "Actualizado_el"
            FROM "ACTIVOS" a
            LEFT JOIN "ESTADOS_ACTIVO" e ON e."id" = a."fk_estado"
            LEFT JOIN "ASIGNACIONES" asig ON asig."fk_Activo" = a."id" AND asig."Estado" = 'Activa'
            LEFT JOIN "PERSONAL" p ON p."id" = asig."fk_Personal"
            LEFT JOIN "COMPRAS" c ON c."fk_activo" = a."id"
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW "vw_tarjetas_red" AS
            SELECT
                t."id"                  AS "id",
                t."fk_activo"           AS "fk_activo",
                a."Etiqueta_activo"     AS "Etiqueta_activo",
                a."Nombre_de_activo"    AS "Nombre_de_activo",
                a."IP"                  AS "IP_servidor",
                t."Numero"              AS "Numero",
                t."Nombre"              AS "Nombre",
                t."Direccion_MAC"       AS "Direccion_MAC",
                t."IP"                  AS "IP",
                t."fk_switch"           AS "fk_switch",
                t."Switch"              AS "Switch",
                t."Puerto"              AS "Puerto",
                t."Creado_el"           AS "Creado_el"
            FROM "TARJETAS_RED" t
            LEFT JOIN "ACTIVOS" a ON a."id" = t."fk_activo"
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS "vw_tarjetas_red"');

        DB::statement('DROP VIEW IF EXISTS "vw_inventario"');

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
};
