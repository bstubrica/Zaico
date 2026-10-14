<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUbicacionRequest;
use App\Models\Ubicacion;
use App\Services\UbicacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UbicacionController extends Controller
{
    public function __construct(private readonly UbicacionService $ubicaciones) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['termino', 'estado']);
        $filtros['tamano'] = 50;
        $ubicaciones = $this->ubicaciones->listar($filtros);

        return view('ubicaciones.index', compact('ubicaciones', 'filtros'));
    }

    public function create(): View
    {
        return view('ubicaciones.create');
    }

    public function store(StoreUbicacionRequest $request): RedirectResponse
    {
        $this->ubicaciones->crear($request->validated());

        return redirect()->route('ubicaciones.index')->with('success', 'Ubicación registrada correctamente.');
    }

    public function edit(Ubicacion $ubicacion): View
    {
        return view('ubicaciones.edit', compact('ubicacion'));
    }

    public function update(StoreUbicacionRequest $request, Ubicacion $ubicacion): RedirectResponse
    {
        $this->ubicaciones->actualizar($ubicacion, $request->validated());

        return redirect()->route('ubicaciones.index')->with('success', 'Ubicación actualizada.');
    }

    public function destroy(Ubicacion $ubicacion): RedirectResponse
    {
        $this->ubicaciones->darDeBaja($ubicacion);

        return redirect()->route('ubicaciones.index')->with('success', 'Ubicación dada de baja.');
    }
}
