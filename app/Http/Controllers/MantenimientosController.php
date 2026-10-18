<?php

namespace App\Http\Controllers;

use App\Http\Requests\EliminarMantenimientosRequest;
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

    public function evidencia(EvidenciaMantenimiento $evidencia)
    {
        return $this->mantenimientos->descargarEvidencia($evidencia);
    }

    public function destroy(Request $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $this->mantenimientos->eliminar($mantenimiento, $request->user()->id);

        return back()->with('success', 'Mantenimiento eliminado.');
    }

    public function destroyMasivo(EliminarMantenimientosRequest $request): RedirectResponse
    {
        $ids = $request->boolean('todos')
            ? $this->mantenimientos->idsDeFiltro($request->only(['desde', 'hasta', 'activo']))
            : ($request->validated('ids') ?? []);

        if ($ids === []) {
            return back()->with('error', 'El filtro activo no coincide con ningún mantenimiento.');
        }

        if (count($ids) > MantenimientoService::MAXIMO_LOTE) {
            return back()->with('error', 'La selección supera el límite de '.MantenimientoService::MAXIMO_LOTE.' mantenimientos por operación. Acote el filtro e inténtelo de nuevo.');
        }

        $resultado = $this->mantenimientos->eliminarLote($ids, $request->user()->id);

        $redirect = back()
            ->with('success', "Mantenimientos eliminados: {$resultado['eliminados']} de ".count($ids).' seleccionados.');

        if ($resultado['omitidos'] !== []) {
            $redirect
                ->with('error', 'Algunos mantenimientos no se procesaron (ver detalle).')
                ->with('lote_omitidos', $resultado['omitidos']);
        }

        return $redirect;
    }
}
