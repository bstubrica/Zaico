<?php

namespace App\Http\Controllers;

use App\Enums\TipoDesincorporacion;
use App\Http\Requests\ActualizarDesincorporacionRequest;
use App\Models\Activo;
use App\Models\Desincorporacion;
use App\Services\ActivoService;
use App\Services\DesincorporacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Apartado de equipos desincorporados: listado (solo lectura para cualquier
 * usuario) y edición o reincorporación para Admin / SoporteTecnico.
 */
class DesincorporadosController extends Controller
{
    public function __construct(
        private readonly DesincorporacionService $desincorporaciones,
        private readonly ActivoService $activos,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['termino', 'tipo', 'desde', 'hasta', 'sin_datos']);
        $filtros['tamano'] = 50;
        $desincorporados = $this->desincorporaciones->listar($filtros);

        return view('desincorporados.index', [
            'desincorporados' => $desincorporados,
            'filtros' => $filtros,
            'tipos' => TipoDesincorporacion::opciones(),
        ]);
    }

    public function actualizar(ActualizarDesincorporacionRequest $request, Desincorporacion $desincorporacion): RedirectResponse
    {
        try {
            $this->desincorporaciones->actualizar($desincorporacion, $request->validated(), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['desincorporacion' => $e->getMessage()]);
        }

        return back()->with('success', 'Registro de desincorporación actualizado.');
    }

    public function reincorporar(Activo $activo, Request $request): RedirectResponse
    {
        try {
            $this->activos->reincorporar($activo, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reincorporar' => $e->getMessage()]);
        }

        return back()->with('success', 'Equipo reincorporado al inventario activo.');
    }
}
