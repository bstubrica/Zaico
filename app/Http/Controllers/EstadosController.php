<?php

namespace App\Http\Controllers;

use App\Models\EstadoActivo;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class EstadosController extends Controller
{
    public function index(): View|JsonResponse
    {
        $estados = EstadoActivo::withCount('activos')
            ->where('Estado', 1)
            ->orderBy('Grupo')
            ->orderBy('Nombre')
            ->get();

        if (request()->expectsJson()) {
            return response()->json($estados);
        }

        return view('estados.index', compact('estados'));
    }
}
