<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMantenimientoRequest;
use App\Models\Activo;
use App\Models\EvidenciaMantenimiento;
use App\Models\Mantenimiento;
use App\Services\MantenimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MantenimientosController extends Controller
{
    public function __construct(private readonly MantenimientoService $mantenimientos) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['desde', 'hasta', 'activo']);
        $filtros['tamano'] = 50;
        $mantenimientos = $this->mantenimientos->listarGlobal($filtros);

        return view('mantenimientos.index', compact('mantenimientos', 'filtros'));
    }

    public function porActivo(Activo $activo): View
    {
        $mantenimientos = $this->mantenimientos->listarPorActivo($activo);

        return view('mantenimientos.por-activo', compact('activo', 'mantenimientos'));
    }

    public function store(StoreMantenimientoRequest $request, Activo $activo)
    {
        $this->mantenimientos->crear(
            $activo,
            $request->validated(),
            $request->user()->id,
            $request->file('evidencias', []),
        );

        return back()->with('success', 'Mantenimiento registrado correctamente.');
    }

    public function edit(Mantenimiento $mantenimiento): View
    {
        $mantenimiento->load(['activo', 'evidencias']);

        return view('mantenimientos.edit', compact('mantenimiento'));
    }

    public function update(StoreMantenimientoRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $this->mantenimientos->actualizar(
            $mantenimiento,
            $request->validated(),
            $request->user()->id,
            $request->file('evidencias', []),
        );

        return redirect()
            ->route('activos.show', $mantenimiento->fk_activo)
            ->with('success', 'Mantenimiento actualizado correctamente.');
    }

    public function destroy(Mantenimiento $mantenimiento): RedirectResponse
    {
        $activoId = $mantenimiento->fk_activo;

        $this->mantenimientos->eliminar($mantenimiento, request()->user()->id);

        return redirect()
            ->route('activos.show', $activoId)
            ->with('success', 'Mantenimiento eliminado correctamente.');
    }

    public function evidencia(EvidenciaMantenimiento $evidencia)
    {
        return $this->mantenimientos->descargarEvidencia($evidencia);
    }
}
