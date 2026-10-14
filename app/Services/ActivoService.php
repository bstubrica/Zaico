<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\ModeloActivo;
use App\Models\Personal;
use App\Models\Ubicacion;
use App\Services\Concerns\BusquedaAproximada;
use App\Services\Concerns\LimiteLote;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivoService
{
    use BusquedaAproximada;
    use LimiteLote;
    use PaginacionAcotada;

    public function __construct(private readonly HistorialService $historial) {}

    /**
     * Consulta base del listado de activos con filtros (sin paginar).
     *
     * @param  array{termino?: string, categoria?: string, estado?: string, ubicacion?: string, modelo?: string, asignado_a?: string}  $filtros
     * @return Builder<Activo>
     */
    public function consultaListado(array $filtros): Builder
    {
        return Activo::query()
            ->with(['estadoCatalogo', 'asignacionActiva.personal'])
            ->when($filtros['termino'] ?? null, fn ($query, $termino) => $this->aplicarBusqueda(
                $query,
                ['Nombre_de_activo', 'Serial', 'Etiqueta_activo', 'Modelo'],
                $termino,
            ))
            ->when($filtros['categoria'] ?? null,
                fn ($query, $categoria) => $query->where('Categoria', $categoria))
            ->when($filtros['estado'] ?? null, function ($query, $estado) {
                // El grupo vive denormalizado en `Estado`; `fk_estado` puede
                // faltar en activos importados, así que se considera también.
                $query->where(function ($sub) use ($estado) {
                    $sub->where('Estado', $estado)
                        ->orWhereHas('estadoCatalogo', fn ($where) => $where
                            ->where('Nombre', $estado)
                            ->orWhere('Grupo', $estado));
                });
            })
            ->when($filtros['ubicacion'] ?? null, fn ($query, $ubicacion) => $this->aplicarBusqueda(
                $query,
                ['Ubicacion'],
                $ubicacion,
            ))
            ->when($filtros['modelo'] ?? null, fn ($query, $modelo) => $this->aplicarBusqueda(
                $query,
                ['Modelo'],
                $modelo,
            ))
            ->when($filtros['asignado_a'] ?? null, function ($query, $asignadoA) {
                $query->whereHas('asignacionActiva.personal', fn ($where) => $this->aplicarBusqueda(
                    $where,
                    [DB::raw("trim(\"Nombre\" || ' ' || \"Apellido\")")],
                    $asignadoA,
                ));
            })
            ->orderBy('Nombre_de_activo')
            ->orderBy('id');
    }

    /**
     * @param  array{termino?: string, categoria?: string, estado?: string, ubicacion?: string, modelo?: string, asignado_a?: string, tamano?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return $this->consultaListado($filtros)
            ->paginate($this->tamanoPagina($filtros['tamano'] ?? null))
            ->withQueryString();
    }

    /**
     * IDs de todos los activos que coinciden con el filtro activo, para la
     * opción «seleccionar todos los resultados» del listado.
     *
     * @param  array{termino?: string, categoria?: string, estado?: string, ubicacion?: string, modelo?: string, asignado_a?: string}  $filtros
     * @return array<int, int>
     */
    public function idsDeFiltro(array $filtros): array
    {
        return $this->consultaListado($filtros)->pluck('id')->all();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, int $usuarioId): Activo
    {
        $datos = $this->sincronizarCatalogos($datos);

        return DB::transaction(function () use ($datos, $usuarioId) {
            $activo = Activo::create($datos);

            $this->historial->registrar(
                $activo->id,
                'Creacion',
                "Activo registrado: {$activo->Nombre_de_activo}",
                usuarioId: $usuarioId,
            );

            return $activo->load('estadoCatalogo');
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Activo $activo, array $datos, int $usuarioId): Activo
    {
        $datos = $this->sincronizarCatalogos($datos);

        return DB::transaction(function () use ($activo, $datos, $usuarioId) {
            $antes = $activo->only(array_keys($datos));
            $activo->update($datos);

            $this->historial->registrar(
                $activo->id,
                'Modificacion',
                'Datos del activo modificados',
                anterior: json_encode($antes, JSON_UNESCAPED_UNICODE),
                nuevo: json_encode($activo->only(array_keys($datos)), JSON_UNESCAPED_UNICODE),
                usuarioId: $usuarioId,
            );

            return $activo->fresh('estadoCatalogo');
        });
    }

    public function cambiarEstado(Activo $activo, int $fkEstado, string $motivo, int $usuarioId): Activo
    {
        $estadoNuevo = EstadoActivo::findOrFail($fkEstado);
        $anterior = $activo->estadoCatalogo?->Nombre;

        return DB::transaction(function () use ($activo, $estadoNuevo, $motivo, $usuarioId, $anterior) {
            $activo->update(['Estado' => $estadoNuevo->Grupo, 'fk_estado' => $estadoNuevo->id]);

            $this->historial->registrar(
                $activo->id,
                'CambioEstado',
                $motivo,
                anterior: $anterior,
                nuevo: $estadoNuevo->Nombre,
                usuarioId: $usuarioId,
            );

            return $activo->fresh('estadoCatalogo');
        });
    }

    /**
     * Desincorporación (baja lógica): cambia el estado a «Desincorporado» sin
     * borrar la fila ni su historial.
     */
    public function desincorporar(Activo $activo, string $motivo, int $usuarioId): Activo
    {
        $grupoActual = $activo->estadoCatalogo?->Grupo ?? $activo->Estado;

        if ($activo->asignacionActiva()->exists()) {
            throw new RuntimeException('No se puede desincorporar: el activo tiene una asignación activa. Devuélvalo primero.');
        }

        if ($grupoActual === 'Desincorporado') {
            throw new RuntimeException('El activo ya está desincorporado.');
        }

        $estadoDesincorporado = EstadoActivo::where('Grupo', 'Desincorporado')->first();

        if ($estadoDesincorporado === null) {
            throw new RuntimeException('No hay un estado de desincorporación en el catálogo; consulte al administrador.');
        }

        return $this->cambiarEstado($activo, $estadoDesincorporado->id, $motivo, $usuarioId);
    }

    /**
     * Desincorpora (baja lógica) un lote de activos de forma atómica: o se
     * desincorporan todos los procesables o no se aplica ninguno. Los que no
     * cumplen las reglas (asignación activa o ya desincorporados) se excluyen
     * antes de abrir la transacción y se detallan, para que una omisión
     * esperada no anule el resto del lote.
     *
     * @param  array<int, int>  $ids
     * @return array{desincorporados: int, omitidos: array<int, array{label: string, motivo: string}>}
     */
    public function desincorporarLote(array $ids, string $motivo, int $usuarioId): array
    {
        $estadoDesincorporado = EstadoActivo::where('Grupo', 'Desincorporado')->first();

        if ($estadoDesincorporado === null) {
            throw new RuntimeException('No hay un estado de desincorporación en el catálogo; consulte al administrador.');
        }

        $activos = Activo::query()
            ->with(['estadoCatalogo', 'asignacionActiva'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $procesables = [];
        $omitidos = [];

        foreach ($ids as $id) {
            $activo = $activos->get($id);

            if ($activo === null) {
                $omitidos[] = ['label' => "#{$id}", 'motivo' => 'No se encontró en el inventario.'];

                continue;
            }

            if ($activo->asignacionActiva !== null) {
                $omitidos[] = ['label' => $activo->Etiqueta_activo, 'motivo' => 'Tiene una asignación activa; devuelva el equipo primero.'];

                continue;
            }

            $grupoActual = $activo->estadoCatalogo?->Grupo ?? $activo->Estado;

            if ($grupoActual === 'Desincorporado') {
                $omitidos[] = ['label' => $activo->Etiqueta_activo, 'motivo' => 'Ya está desincorporado.'];

                continue;
            }

            $procesables[] = $activo;
        }

        DB::transaction(function () use ($procesables, $estadoDesincorporado, $motivo, $usuarioId): void {
            foreach ($procesables as $activo) {
                $this->cambiarEstado($activo, $estadoDesincorporado->id, $motivo, $usuarioId);
            }
        });

        return ['desincorporados' => count($procesables), 'omitidos' => $omitidos];
    }

    /**
     * @param  array{fk_Personal: int, Fecha_asignacion: string, Observaciones?: string|null}  $datos
     */
    public function asignar(Activo $activo, array $datos, int $usuarioId): Activo
    {
        if ($activo->asignacionActiva()->exists()) {
            throw new RuntimeException('El activo ya tiene una asignación activa. Devuélvalo primero.');
        }

        // El grupo vive denormalizado en `Estado`; `fk_estado` puede faltar en
        // activos creados sin detalle de catálogo, así que no basta con mirar
        // la relación para bloquear la asignación de un desincorporado.
        $grupoActual = $activo->estadoCatalogo?->Grupo ?? $activo->Estado;

        if ($grupoActual === 'Desincorporado') {
            throw new RuntimeException('No se puede asignar un activo desincorporado.');
        }

        $personal = Personal::findOrFail($datos['fk_Personal']);

        if ($personal->Estado !== 'Activo') {
            throw new RuntimeException('No se puede asignar a una persona inactiva.');
        }

        return DB::transaction(function () use ($activo, $datos, $usuarioId, $personal) {
            try {
                Asignacion::create([
                    'fk_Activo' => $activo->id,
                    'fk_Personal' => $personal->id,
                    'fk_usuario' => $usuarioId,
                    'Fecha_asignacion' => $datos['Fecha_asignacion'],
                    'Observaciones' => $datos['Observaciones'] ?? null,
                    'Estado' => 'Activa',
                ]);
            } catch (QueryException $e) {
                // Índice único parcial `asignaciones_activa_unica`: otra
                // solicitud concurrente ya creó una asignación activa.
                if (($e->errorInfo[0] ?? $e->getCode()) === '23505') {
                    throw new RuntimeException('El activo ya tiene una asignación activa. Devuélvalo primero.');
                }

                throw $e;
            }

            $estadoAsignado = EstadoActivo::where('Nombre', 'like', 'Asignado%')->first();

            if ($estadoAsignado) {
                $activo->update(['Estado' => $estadoAsignado->Grupo, 'fk_estado' => $estadoAsignado->id]);
            }

            $this->historial->registrar(
                $activo->id,
                'Asignacion',
                "Asignado a {$personal->NombreCompleto}",
                usuarioId: $usuarioId,
            );

            return $activo->fresh(['estadoCatalogo', 'asignacionActiva.personal']);
        });
    }

    /**
     * @param  array{Fecha_devolucion: string, Observaciones?: string|null}  $datos
     */
    public function devolver(Activo $activo, array $datos, int $usuarioId): Activo
    {
        $asignacion = $activo->asignacionActiva()->with('personal')->first();

        if (! $asignacion) {
            throw new RuntimeException('No hay asignación activa para este activo.');
        }

        return DB::transaction(function () use ($activo, $asignacion, $datos, $usuarioId) {
            $asignacion->update([
                'Fecha_devolucion' => $datos['Fecha_devolucion'],
                'Observaciones' => trim(
                    ($asignacion->Observaciones ? $asignacion->Observaciones.' | ' : '').($datos['Observaciones'] ?? '')
                ),
                'Estado' => 'Cerrada',
            ]);

            $estadoDisponible = EstadoActivo::where('Grupo', 'Disponible')->first();

            if ($estadoDisponible) {
                $activo->update(['Estado' => $estadoDisponible->Grupo, 'fk_estado' => $estadoDisponible->id]);
            }

            $this->historial->registrar(
                $activo->id,
                'Devolucion',
                'Devuelto por '.($asignacion->personal?->NombreCompleto ?? 'desconocido'),
                usuarioId: $usuarioId,
            );

            return $activo->fresh(['estadoCatalogo', 'asignacionActiva.personal']);
        });
    }

    /**
     * @return Collection<int, Asignacion>
     */
    public function historialAsignaciones(Activo $activo): Collection
    {
        return $activo->asignaciones()->with('personal')->orderByDesc('Fecha_asignacion')->get();
    }

    /**
     * Sincroniza los campos de texto denormalizados (`Ubicacion` y `Modelo`)
     * con la ubicación y el modelo elegidos del catálogo, que son los que
     * consumen los reportes y Power BI. Si el catálogo se deja vacío, el texto
     * también se limpia. Cuando el formulario no envía la clave, no se toca.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function sincronizarCatalogos(array $datos): array
    {
        if (array_key_exists('fk_ubicacion', $datos)) {
            $datos['fk_ubicacion'] = $this->idOpcional($datos['fk_ubicacion']);
            $datos['Ubicacion'] = $datos['fk_ubicacion'] === null
                ? null
                : Ubicacion::whereKey($datos['fk_ubicacion'])->value('Nombre');
        }

        if (array_key_exists('fk_modelo', $datos)) {
            $datos['fk_modelo'] = $this->idOpcional($datos['fk_modelo']);
            $datos['Modelo'] = $datos['fk_modelo'] === null
                ? null
                : ModeloActivo::whereKey($datos['fk_modelo'])->value('Nombre');
        }

        return $datos;
    }

    private function idOpcional(mixed $valor): ?int
    {
        return ($valor === null || $valor === '' || $valor === '0') ? null : (int) $valor;
    }

    /**
     * Busca un activo por su etiqueta o número de serie (coincidencia exacta,
     * insensible a mayúsculas y espacios), pensado para lectores de códigos.
     */
    public function buscarPorCodigo(string $codigo): ?Activo
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        return Activo::query()
            ->with(['estadoCatalogo', 'asignacionActiva.personal'])
            ->where(function (Builder $query) use ($codigo) {
                $query->whereRaw('lower("Etiqueta_activo") = lower(?)', [$codigo])
                    ->orWhereRaw('lower("Serial") = lower(?)', [$codigo]);
            })
            ->first();
    }
}
