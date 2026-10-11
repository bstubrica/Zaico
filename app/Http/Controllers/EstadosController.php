<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstadoActivoRequest;
use App\Models\EstadoActivo;
use App\Services\EstadoActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EstadosController extends Controller
{
    public function __construct(private readonly EstadoActivoService $estados) {}

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

    public function create(): View
    {
        return view('estados.create');
    }

    public function store(StoreEstadoActivoRequest $request): RedirectResponse
    {
        $this->estados->crear($request->validated());

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado agregado al catálogo.');
    }

    public function edit(EstadoActivo $estado): View
    {
        return view('estados.edit', compact('estado'));
    }

    public function update(StoreEstadoActivoRequest $request, EstadoActivo $estado): RedirectResponse
    {
        $this->estados->actualizar($estado, $request->validated());

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(EstadoActivo $estado): RedirectResponse
    {
        $this->estados->eliminar($estado);

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado eliminado del catálogo.');
    }
}
