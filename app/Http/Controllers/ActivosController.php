<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarEquipoRequest;
use App\Http\Requests\CambiarEstadoRequest;
use App\Http\Requests\DesincorporarActivoRequest;
use App\Http\Requests\DesincorporarActivosMasivoRequest;
use App\Http\Requests\DevolverEquipoRequest;
use App\Http\Requests\GuardarCompraRequest;
use App\Http\Requests\StoreActivoRequest;
use App\Http\Requests\UpdateActivoRequest;
use App\Models\Activo;
use App\Models\EstadoActivo;
use App\Models\ModeloActivo;
use App\Models\Personal;
use App\Models\Ubicacion;
use App\Services\ActivoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ActivosController extends Controller
{
    /**
     * Tope de eventos devueltos por el endpoint de auditoría para evitar
     * respuestas desmedidas en equipos con mucho historial.
     */
    private const MAX_EVENTOS = 200;

    public function __construct(private readonly ActivoService $activos) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['termino', 'categoria', 'estado', 'ubicacion', 'modelo', 'asignado_a']);
        $filtros['tamano'] = 50;
        $activos = $this->activos->listar($filtros);

        $grupos = EstadoActivo::where('Estado', 1)->distinct()->orderBy('Grupo')->pluck('Grupo');
        $categorias = Activo::whereNotNull('Categoria')->distinct()->orderBy('Categoria')->pluck('Categoria');
        $ubicaciones = $this->opcionesUbicaciones();
        $modelos = $this->opcionesModelos();

        return view('activos.index', compact('activos', 'filtros', 'grupos', 'categorias', 'ubicaciones', 'modelos'));
    }

    public function show(Activo $activo): View
    {
        $activo->load(['estadoCatalogo', 'asignacionActiva.personal', 'mantenimientos.evidencias', 'compra', 'ubicacionCatalogo', 'modeloCatalogo']);
        $historial = $this->activos->historialAsignaciones($activo);
        $personas = Personal::where('Estado', 'Activo')->orderBy('Apellido')->get();
        $estados = $this->catalogoEstados($activo);

        return view('activos.show', compact('activo', 'historial', 'personas', 'estados'));
    }

    public function buscar(Request $request): View|RedirectResponse
    {
        $codigo = trim((string) $request->query('codigo', ''));

        if ($codigo === '') {
            return view('activos.buscar', ['codigo' => null]);
        }

        $activo = $this->activos->buscarPorCodigo($codigo);

        if ($activo === null) {
            return redirect()
                ->route('activos.buscar')
                ->withInput()
                ->withErrors(['codigo' => "No se encontró ningún equipo con la etiqueta o serial «{$codigo}»."]);
        }

        return redirect()->route('activos.show', $activo);
    }

    public function create(): View
    {
        $estados = $this->catalogoEstados();
        $grupos = EstadoActivo::where('Estado', 1)->distinct()->orderBy('Grupo')->pluck('Grupo');
        $ubicaciones = $this->opcionesUbicaciones();
        $modelos = $this->opcionesModelos();

        return view('activos.create', compact('estados', 'grupos', 'ubicaciones', 'modelos'));
    }

    public function store(StoreActivoRequest $request)
    {
        $this->activos->crear(
            $request->safe()->except('imagen'),
            $request->user()->id,
            $request->file('imagen'),
        );

        return redirect()->route('activos.index')->with('success', 'Activo registrado.');
    }

    public function edit(Activo $activo): View
    {
        $estados = $this->catalogoEstados($activo);
        $grupos = EstadoActivo::where('Estado', 1)->distinct()->orderBy('Grupo')->pluck('Grupo');
        $ubicaciones = $this->opcionesUbicaciones($activo);
        $modelos = $this->opcionesModelos($activo);

        return view('activos.edit', compact('activo', 'estados', 'grupos', 'ubicaciones', 'modelos'));
    }

    public function update(UpdateActivoRequest $request, Activo $activo)
    {
        $this->activos->actualizar(
            $activo,
            $request->safe()->except('imagen'),
            $request->user()->id,
            $request->file('imagen'),
        );

        return redirect()->route('activos.show', $activo)->with('success', 'Activo actualizado.');
    }

    public function guardarCompra(GuardarCompraRequest $request, Activo $activo): RedirectResponse
    {
        $this->activos->guardarCompra(
            $activo,
            $request->safe()->except('imagen_factura'),
            $request->user()->id,
            $request->file('imagen_factura'),
        );

        return back()->with('success', 'Datos de compra guardados.');
    }

    public function imagen(Activo $activo)
    {
        return $this->activos->respuestaImagen($activo);
    }

    public function imagenFactura(Activo $activo)
    {
        return $this->activos->respuestaImagenFactura($activo);
    }

    public function asignar(AsignarEquipoRequest $request, Activo $activo)
    {
        try {
            $this->activos->asignar($activo, $request->validated(), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['asignacion' => $e->getMessage()]);
        }

        return back()->with('success', 'Equipo asignado.');
    }

    public function devolver(DevolverEquipoRequest $request, Activo $activo)
    {
        try {
            $this->activos->devolver($activo, $request->validated(), $request->user()->id);
        } catch (RuntimeException $e) {
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

    public function desincorporar(DesincorporarActivoRequest $request, Activo $activo): RedirectResponse
    {
        try {
            $this->activos->desincorporar($activo, $request->validated('motivo'), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['desincorporar' => $e->getMessage()]);
        }

        return back()->with('success', 'Equipo desincorporado.');
    }

    public function desincorporarMasivo(DesincorporarActivosMasivoRequest $request): RedirectResponse
    {
        $ids = $request->boolean('todos')
            ? $this->activos->idsDeFiltro($request->only(['termino', 'categoria', 'estado', 'ubicacion', 'modelo', 'asignado_a']))
            : ($request->validated('ids') ?? []);

        if ($ids === []) {
            return back()->with('error', 'El filtro activo no coincide con ningún equipo.');
        }

        if (count($ids) > ActivoService::MAXIMO_LOTE) {
            return back()->with('error', 'La selección supera el límite de '.ActivoService::MAXIMO_LOTE.' equipos por operación. Acote el filtro e inténtelo de nuevo.');
        }

        try {
            $resultado = $this->activos->desincorporarLote($ids, $request->validated('motivo'), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['desincorporar' => $e->getMessage()]);
        }

        $redirect = back()
            ->with('success', "Equipos desincorporados: {$resultado['desincorporados']} de ".count($ids).' seleccionados.');

        if ($resultado['omitidos'] !== []) {
            $redirect
                ->with('error', 'Algunos equipos no se procesaron por reglas de negocio (ver detalle).')
                ->with('lote_omitidos', $resultado['omitidos']);
        }

        return $redirect;
    }

    public function eventos(Activo $activo): JsonResponse
    {
        return response()->json(
            $activo->eventos()
                ->with('usuario:id,name')
                ->orderByDesc('Fecha')
                ->limit(self::MAX_EVENTOS)
                ->get()
        );
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $filtros = $request->only(['termino', 'categoria', 'estado', 'ubicacion', 'modelo', 'asignado_a']);
        $filtros['tamano'] = (int) $request->input('tamano', 50);

        return response()->json($this->activos->listar($filtros));
    }

    /**
     * Catálogo activo de estados. Incluye el estado vinculado al activo aunque
     * esté inactivo, para no perder el valor al editar.
     *
     * @return Collection<int, EstadoActivo>
     */
    private function catalogoEstados(?Activo $activo = null)
    {
        $seleccionado = $activo?->fk_estado;

        return EstadoActivo::query()
            ->where(function ($query) use ($seleccionado) {
                $query->where('Estado', 1);

                if ($seleccionado !== null) {
                    $query->orWhere('id', $seleccionado);
                }
            })
            ->orderBy('Grupo')
            ->orderBy('Nombre')
            ->get();
    }

    /**
     * Incluye la ubicación vinculada al activo aunque esté inactiva, para no
     * perder el valor al editar.
     *
     * @return Collection<int, Ubicacion>
     */
    private function opcionesUbicaciones(?Activo $activo = null)
    {
        $seleccionada = $activo?->fk_ubicacion;

        return Ubicacion::query()
            ->where(function ($query) use ($seleccionada) {
                $query->where('Estado', 'Activo');

                if ($seleccionada !== null) {
                    $query->orWhere('id', $seleccionada);
                }
            })
            ->orderBy('Nombre')
            ->get();
    }

    /**
     * Incluye el modelo vinculado al activo aunque esté inactivo, para no
     * perder el valor al editar.
     *
     * @return Collection<int, ModeloActivo>
     */
    private function opcionesModelos(?Activo $activo = null)
    {
        $seleccionado = $activo?->fk_modelo;

        return ModeloActivo::query()
            ->where(function ($query) use ($seleccionado) {
                $query->where('Estado', 'Activo');

                if ($seleccionado !== null) {
                    $query->orWhere('id', $seleccionado);
                }
            })
            ->orderBy('Nombre')
            ->get();
    }
}
