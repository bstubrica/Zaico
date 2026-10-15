<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\EstadoActivo;
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
        return EstadoActivo::query()
            ->join('ACTIVOS', 'ACTIVOS.fk_estado', '=', 'ESTADOS_ACTIVO.id')
            ->select('ESTADOS_ACTIVO.Grupo as grupo')
            ->selectRaw('count(*) as total')
            ->groupBy('ESTADOS_ACTIVO.Grupo')
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
