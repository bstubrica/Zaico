<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstadoRequest;
use App\Models\EstadoActivo;
use App\Services\EstadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EstadosController extends Controller
{
    public function __construct(private readonly EstadoService $estados) {}

    public function index(): View|JsonResponse
    {
        $estados = $this->estados->listar();

        if (request()->expectsJson()) {
            return response()->json($estados);
        }

        return view('estados.index', compact('estados'));
    }

    public function create(): View
    {
        return view('estados.create');
    }

    public function store(StoreEstadoRequest $request): RedirectResponse
    {
        $this->estados->crear($request->validated());

        return redirect()->route('estados.index')->with('success', 'Estado registrado correctamente.');
    }

    public function edit(EstadoActivo $estado): View
    {
        return view('estados.edit', compact('estado'));
    }

    public function update(StoreEstadoRequest $request, EstadoActivo $estado): RedirectResponse
    {
        $this->estados->actualizar($estado, $request->validated());

        return redirect()->route('estados.index')->with('success', 'Estado actualizado.');
    }

    public function destroy(EstadoActivo $estado): RedirectResponse
    {
        $this->estados->eliminar($estado);

        return redirect()->route('estados.index')->with('success', 'Estado eliminado.');
    }
}
