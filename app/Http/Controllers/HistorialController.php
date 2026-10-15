<?php

namespace App\Http\Controllers;

use App\Services\HistorialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function __construct(private readonly HistorialService $historial) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['desde', 'hasta', 'personal_id']);

        $asignaciones = $this->historial->consultaAsignaciones($filtros)
            ->paginate(50)
            ->withQueryString();

        $personas = $this->historial->catalogoPersonal();

        return view('historial.index', compact('asignaciones', 'filtros', 'personas'));
    }
}
