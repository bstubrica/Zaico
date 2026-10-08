<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\EstadoActivo;
use App\Models\Personal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivoService
{
    public function __construct(private readonly HistorialService $historial) {}

    /**
     * @param  array{termino?: string, categoria?: string, estado?: string, ubicacion?: string, asignado_a?: string, tamano?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Activo::query()
            ->with(['estadoCatalogo', 'asignacionActiva.personal'])
            ->when($filtros['termino'] ?? null, function ($query, $termino) {
                $query->where(fn ($where) => $where
                    ->where('Nombre_de_activo', 'ilike', "%{$termino}%")
                    ->orWhere('Serial', 'ilike', "%{$termino}%")
                    ->orWhere('Etiqueta_activo', 'ilike', "%{$termino}%")
                    ->orWhere('Modelo', 'ilike', "%{$termino}%"));
            })
            ->when($filtros['categoria'] ?? null,
                fn ($query, $categoria) => $query->where('Categoria', $categoria))
            ->when($filtros['estado'] ?? null, function ($query, $estado) {
                $query->whereHas('estadoCatalogo', fn ($where) => $where->where('Nombre', $estado)->orWhere('Grupo', $estado));
            })
            ->when($filtros['ubicacion'] ?? null,
                fn ($query, $ubicacion) => $query->where('Ubicacion', 'ilike', "%{$ubicacion}%"))
            ->when($filtros['asignado_a'] ?? null, function ($query, $asignadoA) {
                $query->whereHas('asignacionActiva.personal', fn ($where) => $where->whereRaw("trim(Nombre || ' ' || Apellido) ilike ?", ["%{$asignadoA}%"]));
            })
            ->orderBy('Nombre_de_activo')
            ->paginate($filtros['tamano'] ?? 50)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, int $usuarioId): Activo
    {
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
     * @param  array{fk_Personal: int, Fecha_asignacion: string, Observaciones?: string|null}  $datos
     */
    public function asignar(Activo $activo, array $datos, int $usuarioId): Activo
    {
        if ($activo->asignacionActiva()->exists()) {
            throw new RuntimeException('El activo ya tiene una asignación activa. Devuélvalo primero.');
        }

        if ($activo->estadoCatalogo?->Grupo === 'Desincorporado') {
            throw new RuntimeException('No se puede asignar un activo desincorporado.');
        }

        $personal = Personal::findOrFail($datos['fk_Personal']);

        if ($personal->Estado !== 'Activo') {
            throw new RuntimeException('No se puede asignar a una persona inactiva.');
        }

        return DB::transaction(function () use ($activo, $datos, $usuarioId, $personal) {
            Asignacion::create([
                'fk_Activo' => $activo->id,
                'fk_Personal' => $personal->id,
                'fk_usuario' => $usuarioId,
                'Fecha_asignacion' => $datos['Fecha_asignacion'],
                'Observaciones' => $datos['Observaciones'] ?? null,
                'Estado' => 'Activa',
            ]);

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
}
