<?php

namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->only(['desde', 'hasta', 'personal_id']);

        $asignaciones = Asignacion::query()
            ->with(['activo:id,Nombre_de_activo,Etiqueta_activo,Estado', 'personal:id,Nombre,Apellido', 'usuario:id,name'])
            ->when($filtros['desde'] ?? null,
                fn ($query, $desde) => $query->whereDate('Fecha_asignacion', '>=', $desde))
            ->when($filtros['hasta'] ?? null,
                fn ($query, $hasta) => $query->whereDate('Fecha_asignacion', '<=', $hasta))
            ->when($filtros['personal_id'] ?? null,
                fn ($query, $personalId) => $query->where('fk_Personal', $personalId))
            ->orderByDesc('Fecha_asignacion')
            ->paginate(50)
            ->withQueryString();

        $personas = Personal::orderBy('Apellido')->get();

        return view('historial.index', compact('asignaciones', 'filtros', 'personas'));
    }
}
