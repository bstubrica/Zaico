<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarEquipoRequest;
use App\Http\Requests\CambiarEstadoRequest;
use App\Http\Requests\DevolverEquipoRequest;
use App\Http\Requests\StoreActivoRequest;
use App\Http\Requests\UpdateActivoRequest;
use App\Models\Activo;
use App\Models\EstadoActivo;
use App\Models\Personal;
use App\Services\ActivoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivosController extends Controller
{
    public function __construct(private readonly ActivoService $activos) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['termino', 'categoria', 'estado', 'ubicacion', 'asignado_a']);
        $filtros['tamano'] = 50;
        $activos = $this->activos->listar($filtros);

        $grupos = EstadoActivo::where('Estado', 1)->distinct()->orderBy('Grupo')->pluck('Grupo');
        $categorias = Activo::whereNotNull('Categoria')->distinct()->orderBy('Categoria')->pluck('Categoria');

        return view('activos.index', compact('activos', 'filtros', 'grupos', 'categorias'));
    }

    public function show(Activo $activo): View
    {
        $activo->load(['estadoCatalogo', 'asignacionActiva.personal', 'mantenimientos.evidencias', 'compra']);
        $historial = $this->activos->historialAsignaciones($activo);
        $personas = Personal::where('Estado', 'Activo')->orderBy('Apellido')->get();
        $estados = $this->catalogoEstados();

        return view('activos.show', compact('activo', 'historial', 'personas', 'estados'));
    }

    public function create(): View
    {
        $estados = $this->catalogoEstados();
        $grupos = EstadoActivo::where('Estado', 1)->distinct()->orderBy('Grupo')->pluck('Grupo');

        return view('activos.create', compact('estados', 'grupos'));
    }

    public function store(StoreActivoRequest $request)
    {
        $this->activos->crear($request->validated(), $request->user()->id);

        return redirect()->route('activos.index')->with('success', 'Activo registrado correctamente.');
    }

    public function edit(Activo $activo): View
    {
        $estados = $this->catalogoEstados();
        $grupos = EstadoActivo::where('Estado', 1)->distinct()->orderBy('Grupo')->pluck('Grupo');

        return view('activos.edit', compact('activo', 'estados', 'grupos'));
    }

    public function update(UpdateActivoRequest $request, Activo $activo)
    {
        $this->activos->actualizar($activo, $request->validated(), $request->user()->id);

        return redirect()->route('activos.show', $activo)->with('success', 'Activo actualizado.');
    }

    public function asignar(AsignarEquipoRequest $request, Activo $activo)
    {
        try {
            $this->activos->asignar($activo, $request->validated(), $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['asignacion' => $e->getMessage()]);
        }

        return back()->with('success', 'Equipo asignado.');
    }

    public function devolver(DevolverEquipoRequest $request, Activo $activo)
    {
        try {
            $this->activos->devolver($activo, $request->validated(), $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['devolucion' => $e->getMessage()]);
        }

        return back()->with('success', 'Equipo devuelto.');
    }

    public function cambiarEstado(CambiarEstadoRequest $request, Activo $activo)
    {
        $this->activos->cambiarEstado(
            $activo,
            $request->validated('fk_estado'),
            $request->validated('motivo'),
            $request->user()->id,
        );

        return back()->with('success', 'Estado actualizado.');
    }

    public function eventos(Activo $activo): JsonResponse
    {
        return response()->json(
            $activo->eventos()->with('usuario:id,name')->orderByDesc('Fecha')->get()
        );
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $filtros = $request->only(['termino', 'categoria', 'estado', 'ubicacion', 'asignado_a']);
        $filtros['tamano'] = (int) $request->input('tamano', 50);

        return response()->json($this->activos->listar($filtros));
    }

    /**
     * @return Collection<int, EstadoActivo>
     */
    private function catalogoEstados()
    {
        return EstadoActivo::where('Estado', 1)->orderBy('Grupo')->orderBy('Nombre')->get();
    }
}
