<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\EvidenciaMantenimiento;
use App\Models\Mantenimiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MantenimientoService
{
    private const DIRECTORIO = 'uploads/mantenimientos';

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
                $ruta = $archivo->store(self::DIRECTORIO, 'public');

                EvidenciaMantenimiento::create([
                    'fk_mantenimiento' => $mantenimiento->id,
                    'Ruta_archivo' => $ruta,
                    'Nombre_original' => $archivo->getClientOriginalName(),
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
     * @param  array{desde?: string|null, hasta?: string|null, activo?: string|null, tamano?: int}  $filtros
     */
    public function listarGlobal(array $filtros): LengthAwarePaginator
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
            ->paginate($filtros['tamano'] ?? 50)
            ->withQueryString();
    }

    /**
     * Descarga una evidencia solo a usuarios autenticados.
     */
    public function descargarEvidencia(EvidenciaMantenimiento $evidencia)
    {
        abort_unless(
            Storage::disk('public')->exists($evidencia->Ruta_archivo),
            404,
            'El archivo de evidencia ya no está disponible.'
        );

        return Storage::disk('public')->download(
            $evidencia->Ruta_archivo,
            $evidencia->Nombre_original
        );
    }
}
