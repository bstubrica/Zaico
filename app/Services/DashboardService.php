<?php

namespace App\Services;

use App\Models\Activo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Consultas agregadas del panel de inicio. Se mantienen fuera del controlador
 * para que este solo orqueste la vista y las consultas sean reutilizables y
 * portables entre PostgreSQL (producción) y SQLite (desarrollo local).
 */
class DashboardService
{
    /**
     * Cantidad de activos por grupo de estado.
     *
     * @return BaseCollection<string, int>
     */
    public function totalesPorGrupo(): BaseCollection
    {
        // Se parte de ACTIVOS con un LEFT JOIN al catálogo: `fk_estado` es
        // nullable (activos importados sin detalle de catálogo), así que el
        // INNER JOIN perdía filas y los subtotales no sumaban el total. El
        // grupo se resuelve con la misma lógica dual del listado: catálogo
        // vinculado o, en su defecto, el estado denormalizado del activo.
        return Activo::query()
            ->leftJoin('ESTADOS_ACTIVO', 'ACTIVOS.fk_estado', '=', 'ESTADOS_ACTIVO.id')
            ->selectRaw('COALESCE("ESTADOS_ACTIVO"."Grupo", "ACTIVOS"."Estado") as grupo')
            ->selectRaw('count(*) as total')
            ->groupByRaw('COALESCE("ESTADOS_ACTIVO"."Grupo", "ACTIVOS"."Estado")')
            ->pluck('total', 'grupo');
    }

    /**
     * @return Collection<int, Activo>
     */
    public function recientes(int $limite = 10): Collection
    {
        return Activo::query()
            ->with(['estadoCatalogo', 'asignacionActiva.personal'])
            ->orderByDesc('Creado_el')
            ->limit($limite)
            ->get();
    }

    public function total(): int
    {
        return Activo::query()->count();
    }
}
