<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\EvidenciaMantenimiento;
use App\Models\Mantenimiento;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MantenimientoService
{
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
        return Mantenimiento::query()
            ->with(['activo:id,Nombre_de_activo,Etiqueta_activo', 'usuario:id,name', 'evidencias'])
            ->when($filtros['desde'] ?? null,
                fn ($query, $desde) => $query->whereDate('Fecha_mantenimiento', '>=', $desde))
            ->when($filtros['hasta'] ?? null,
                fn ($query, $hasta) => $query->whereDate('Fecha_mantenimiento', '<=', $hasta))
            ->when($filtros['activo'] ?? null, function ($query, $activo) {
                $query->whereHas('activo', fn ($where) => $where
                    ->where('Nombre_de_activo', 'ilike', "%{$activo}%")
                    ->orWhere('Etiqueta_activo', 'ilike', "%{$activo}%"));
            })
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
}
