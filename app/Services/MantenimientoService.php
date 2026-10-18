<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\EvidenciaMantenimiento;
use App\Models\Mantenimiento;
use App\Services\Concerns\BusquedaAproximada;
use App\Services\Concerns\LimiteLote;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MantenimientoService
{
    use BusquedaAproximada;
    use LimiteLote;
    use PaginacionAcotada;

    private const DIRECTORIO = 'uploads/mantenimientos';

    /**
     * Disco privado: las evidencias solo se sirven a través del controlador
     * autenticado, nunca como archivos estáticos públicos.
     */
    private const DISCO = 'local';

    public function __construct(private readonly HistorialService $historial) {}

    /**
     * @param  array{Tipo: string, Descripcion: string, Fecha_mantenimiento: string}  $datos
     * @param  array<int, UploadedFile>  $archivos
     */
    public function crear(Activo $activo, array $datos, int $usuarioId, array $archivos = []): Mantenimiento
    {
        return DB::transaction(function () use ($activo, $datos, $usuarioId, $archivos) {
            $mantenimiento = Mantenimiento::create([
                'fk_activo' => $activo->id,
                'Tipo' => $datos['Tipo'],
                'Descripcion' => $datos['Descripcion'],
                'Fecha_mantenimiento' => $datos['Fecha_mantenimiento'],
                'fk_usuario' => $usuarioId,
            ]);

            foreach ($archivos as $archivo) {
                $ruta = $archivo->store(self::DIRECTORIO, self::DISCO);

                EvidenciaMantenimiento::create([
                    'fk_mantenimiento' => $mantenimiento->id,
                    'Ruta_archivo' => $ruta,
                    'Nombre_original' => basename($archivo->getClientOriginalName()),
                    'Tipo_mime' => $archivo->getMimeType(),
                    'Tamano_bytes' => $archivo->getSize(),
                ]);
            }

            $this->historial->registrar(
                $activo->id,
                'Mantenimiento',
                "Mantenimiento {$datos['Tipo']}: {$datos['Descripcion']}",
                usuarioId: $usuarioId,
            );

            return $mantenimiento->load('evidencias');
        });
    }

    public function listarPorActivo(Activo $activo): Collection
    {
        return $activo->mantenimientos()
            ->with(['evidencias', 'usuario:id,name'])
            ->orderByDesc('Fecha_mantenimiento')
            ->get();
    }

    /**
     * Consulta base del listado global de mantenimientos con filtros (sin paginar).
     *
     * @param  array{desde?: string|null, hasta?: string|null, activo?: string|null}  $filtros
     * @return Builder<Mantenimiento>
     */
    public function consultaGlobal(array $filtros): Builder
    {
        $query = Mantenimiento::query()
            ->with(['activo:id,Nombre_de_activo,Etiqueta_activo', 'usuario:id,name', 'evidencias']);

        $this->aplicarRangoFechas(
            $query,
            'Fecha_mantenimiento',
            $filtros['desde'] ?? null,
            $filtros['hasta'] ?? null,
        );

        $query->when($filtros['activo'] ?? null, function ($query, $activo) {
            $query->whereHas('activo', fn ($where) => $this->aplicarBusqueda(
                $where,
                ['Nombre_de_activo', 'Etiqueta_activo'],
                $activo,
            ));
        });

        return $query
            ->orderByDesc('Fecha_mantenimiento')
            ->orderByDesc('id');
    }

    /**
     * @param  array{desde?: string|null, hasta?: string|null, activo?: string|null, tamano?: int}  $filtros
     */
    public function listarGlobal(array $filtros): LengthAwarePaginator
    {
        return $this->consultaGlobal($filtros)
            ->paginate($this->tamanoPagina($filtros['tamano'] ?? null))
            ->withQueryString();
    }

    /**
     * IDs de todos los mantenimientos que coinciden con el filtro activo, para
     * la opción «seleccionar todos los resultados» del listado.
     *
     * @param  array{desde?: string|null, hasta?: string|null, activo?: string|null}  $filtros
     * @return array<int, int>
     */
    public function idsDeFiltro(array $filtros): array
    {
        return $this->consultaGlobal($filtros)
            ->limit(self::MAXIMO_LOTE + 1)
            ->pluck('id')
            ->all();
    }

    /**
     * Descarga una evidencia solo a usuarios autenticados.
     */
    public function descargarEvidencia(EvidenciaMantenimiento $evidencia)
    {
        abort_unless(
            Storage::disk(self::DISCO)->exists($evidencia->Ruta_archivo),
            404,
            'El archivo de evidencia ya no está disponible.'
        );

        return Storage::disk(self::DISCO)->download(
            $evidencia->Ruta_archivo,
            $evidencia->Nombre_original
        );
    }

    /**
     * Borra el mantenimiento y sus evidencias (filas y archivos en disco).
     * El borrado es una operación auditable: deja traza en HISTORIAL_EVENTOS.
     */
    public function eliminar(Mantenimiento $mantenimiento, int $usuarioId): void
    {
        $rutas = [];

        DB::transaction(function () use ($mantenimiento, $usuarioId, &$rutas) {
            foreach ($mantenimiento->evidencias()->get() as $evidencia) {
                $rutas[] = $evidencia->Ruta_archivo;
            }

            $this->historial->registrar(
                $mantenimiento->fk_activo,
                'Mantenimiento',
                "Mantenimiento eliminado (#{$mantenimiento->id}, {$mantenimiento->Tipo})",
                usuarioId: $usuarioId,
            );

            $mantenimiento->evidencias()->delete();
            $mantenimiento->delete();
        });

        // Los archivos se borran solo tras el commit: si la transacción
        // revierte, las evidencias siguen disponibles en disco.
        foreach ($rutas as $ruta) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    /**
     * Borra un lote de mantenimientos de forma atómica, junto con sus
     * evidencias (filas y archivos en disco). Los IDs inexistentes se detallan
     * como omitidos. Deja traza de cada borrado en HISTORIAL_EVENTOS.
     *
     * @param  array<int, int>  $ids
     * @return array{eliminados: int, omitidos: array<int, array{label: string, motivo: string}>}
     */
    public function eliminarLote(array $ids, int $usuarioId): array
    {
        $mantenimientos = Mantenimiento::query()
            ->with('evidencias')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $procesables = [];
        $omitidos = [];

        foreach ($ids as $id) {
            $mantenimiento = $mantenimientos->get($id);

            if ($mantenimiento === null) {
                $omitidos[] = ['label' => "#{$id}", 'motivo' => 'No se encontró en el registro.'];

                continue;
            }

            $procesables[] = $mantenimiento;
        }

        $rutas = [];

        DB::transaction(function () use ($procesables, $usuarioId, &$rutas): void {
            foreach ($procesables as $mantenimiento) {
                foreach ($mantenimiento->evidencias as $evidencia) {
                    $rutas[] = $evidencia->Ruta_archivo;
                }

                $this->historial->registrar(
                    $mantenimiento->fk_activo,
                    'Mantenimiento',
                    "Mantenimiento eliminado (#{$mantenimiento->id}, {$mantenimiento->Tipo})",
                    usuarioId: $usuarioId,
                );

                $mantenimiento->evidencias()->delete();
                $mantenimiento->delete();
            }
        });

        foreach ($rutas as $ruta) {
            Storage::disk(self::DISCO)->delete($ruta);
        }

        return ['eliminados' => count($procesables), 'omitidos' => $omitidos];
    }
}
