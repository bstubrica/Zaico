<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $porGrupo = Activo::query()
            ->join('ESTADOS_ACTIVO', 'ESTADOS_ACTIVO.id', '=', 'ACTIVOS.fk_estado')
            ->selectRaw('ESTADOS_ACTIVO.Grupo as grupo, COUNT(*) as total')
            ->groupBy('ESTADOS_ACTIVO.Grupo')
            ->pluck('total', 'grupo');

        $recientes = Activo::with(['estadoCatalogo', 'asignacionActiva.personal'])
            ->orderByDesc('Creado_el')
            ->limit(10)
            ->get();

        $total = Activo::count();

        return view('dashboard', compact('porGrupo', 'recientes', 'total'));
    }
}
