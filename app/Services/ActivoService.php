<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Compra;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ActivoService
{
    use BusquedaAproximada;
    use LimiteLote;
    use PaginacionAcotada;

    private const DIRECTORIO_IMAGENES = 'uploads/activos';

    private const DIRECTORIO_FACTURAS = 'uploads/facturas';

    /**
     * Disco privado: las imágenes del activo y de las facturas solo se sirven
     * a través del controlador autenticado, nunca como archivos estáticos
     * públicos.
     */
    private const DISCO = 'local';

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
     * Se limita a `MAXIMO_LOTE + 1` filas: basta para detectar en el
     * controlador que la selección excede el tope, sin materializar en memoria
     * el universo completo de coincidencias.
     *
     * @param  array{termino?: string, categoria?: string, estado?: string, ubicacion?: string, modelo?: string, asignado_a?: string}  $filtros
     * @return array<int, int>
     */
    public function idsDeFiltro(array $filtros): array
    {
        return $this->consultaListado($filtros)
            ->limit(self::MAXIMO_LOTE + 1)
            ->pluck('id')
            ->all();
    }

    /**
     * Catálogo activo de estados. Incluye el estado vinculado al activo aunque
     * esté inactivo, para no perder el valor al editar.
     *
     * @return Collection<int, EstadoActivo>
     */
    public function catalogoEstados(?Activo $activo = null): Collection
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
     * Grupos de estado distintos y activos, para los filtros del listado.
     *
     * @return BaseCollection<int, string>
     */
    public function gruposEstado(): BaseCollection
    {
        return EstadoActivo::query()
            ->where('Estado', 1)
            ->distinct()
            ->orderBy('Grupo')
            ->pluck('Grupo');
    }

    /**
     * Categorías distintas presentes en el inventario.
     *
     * @return BaseCollection<int, string>
     */
    public function categorias(): BaseCollection
    {
        return Activo::query()
            ->whereNotNull('Categoria')
            ->distinct()
            ->orderBy('Categoria')
            ->pluck('Categoria');
    }

    /**
     * Incluye la ubicación vinculada al activo aunque esté inactiva, para no
     * perder el valor al editar.
     *
     * @return Collection<int, Ubicacion>
     */
    public function opcionesUbicaciones(?Activo $activo = null): Collection
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
    public function opcionesModelos(?Activo $activo = null): Collection
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

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, int $usuarioId, ?UploadedFile $imagen = null): Activo
    {
        $datos = $this->sincronizarCatalogos($datos);

        return DB::transaction(function () use ($datos, $usuarioId, $imagen) {
            $activo = Activo::create($datos);

            $this->guardarImagen($activo, $imagen);

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
    public function actualizar(Activo $activo, array $datos, int $usuarioId, ?UploadedFile $imagen = null): Activo
    {
        $datos = $this->sincronizarCatalogos($datos);

        return DB::transaction(function () use ($activo, $datos, $usuarioId, $imagen) {
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

            if ($imagen !== null) {
                $this->guardarImagen($activo, $imagen);

                $this->historial->registrar(
                    $activo->id,
                    'Modificacion',
                    'Imagen del activo actualizada',
                    usuarioId: $usuarioId,
                );
            }

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
     * Historial de asignaciones del activo, paginado para no cargar en memoria
     * un histórico potencialmente largo.
     */
    public function historialAsignaciones(Activo $activo, int $tamano = 15): LengthAwarePaginator
    {
        return $activo->asignaciones()
            ->with('personal')
            ->orderByDesc('Fecha_asignacion')
            ->paginate($tamano)
            ->withQueryString();
    }

    /**
     * Crea o actualiza los datos de compra (relación 1:1) del activo.
     *
     * @param  array<string, mixed>  $datos
     */
    public function guardarCompra(Activo $activo, array $datos, int $usuarioId, ?UploadedFile $factura = null): Compra
    {
        return DB::transaction(function () use ($activo, $datos, $usuarioId, $factura) {
            $compra = $activo->compra()->updateOrCreate([], $datos);

            $this->guardarImagenFactura($compra, $factura);

            $this->historial->registrar(
                $activo->id,
                'Modificacion',
                'Datos de compra actualizados',
                usuarioId: $usuarioId,
            );

            return $compra;
        });
    }

    /**
     * Sirve la imagen del activo desde el disco privado a usuarios
     * autenticados.
     */
    public function respuestaImagen(Activo $activo)
    {
        abort_unless(
            $activo->Imagen_Activo !== null
                && Storage::disk(self::DISCO)->exists($activo->Imagen_Activo),
            404,
            'El activo no tiene una imagen adjunta.'
        );

        return Storage::disk(self::DISCO)->response($activo->Imagen_Activo);
    }

    /**
     * Sirve la fotografía de la factura de compra desde el disco privado a
     * usuarios autenticados.
     */
    public function respuestaImagenFactura(Activo $activo)
    {
        abort_unless(
            $activo->compra?->Imagen_Factura !== null
                && Storage::disk(self::DISCO)->exists($activo->compra->Imagen_Factura),
            404,
            'La compra no tiene una fotografía de factura adjunta.'
        );

        return Storage::disk(self::DISCO)->response($activo->compra->Imagen_Factura);
    }

    /**
     * Guarda la imagen del activo en el disco privado, reemplazando y
     * eliminando la anterior si existía.
     */
    private function guardarImagen(Activo $activo, ?UploadedFile $imagen): void
    {
        if ($imagen === null) {
            return;
        }

        if ($activo->Imagen_Activo !== null) {
            Storage::disk(self::DISCO)->delete($activo->Imagen_Activo);
        }

        $activo->update([
            'Imagen_Activo' => $imagen->store(self::DIRECTORIO_IMAGENES, self::DISCO),
        ]);
    }

    /**
     * Guarda la fotografía de la factura en el disco privado, reemplazando y
     * eliminando la anterior si existía.
     */
    private function guardarImagenFactura(Compra $compra, ?UploadedFile $factura): void
    {
        if ($factura === null) {
            return;
        }

        if ($compra->Imagen_Factura !== null) {
            Storage::disk(self::DISCO)->delete($compra->Imagen_Factura);
        }

        $compra->update([
            'Imagen_Factura' => $factura->store(self::DIRECTORIO_FACTURAS, self::DISCO),
        ]);
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
