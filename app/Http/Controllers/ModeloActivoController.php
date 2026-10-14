<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModeloActivoRequest;
use App\Models\ModeloActivo;
use App\Services\ModeloActivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModeloActivoController extends Controller
{
    public function __construct(private readonly ModeloActivoService $modelos) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['termino', 'estado']);
        $filtros['tamano'] = 50;
        $modelos = $this->modelos->listar($filtros);

        return view('modelos.index', compact('modelos', 'filtros'));
    }

    public function create(): View
    {
        return view('modelos.create');
    }

    public function store(StoreModeloActivoRequest $request): RedirectResponse
    {
        $this->modelos->crear($request->validated());

        return redirect()->route('modelos.index')->with('success', 'Modelo registrado correctamente.');
    }

    public function edit(ModeloActivo $modelo): View
    {
        return view('modelos.edit', compact('modelo'));
    }

    public function update(StoreModeloActivoRequest $request, ModeloActivo $modelo): RedirectResponse
    {
        $this->modelos->actualizar($modelo, $request->validated());

        return redirect()->route('modelos.index')->with('success', 'Modelo actualizado.');
    }

    public function destroy(ModeloActivo $modelo): RedirectResponse
    {
        $this->modelos->darDeBaja($modelo);

        return redirect()->route('modelos.index')->with('success', 'Modelo dado de baja.');
    }
}
