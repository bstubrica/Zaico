<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Personal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class PersonalService
{
    /**
     * @param  array{termino?: string, estado?: string, tamano?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Personal::query()
            ->withCount(['asignaciones as asignaciones_activas' => fn ($query) => $query->activas()])
            ->when($filtros['termino'] ?? null, function ($query, $termino) {
                $query->where(fn ($where) => $where
                    ->where('Nombre', 'ilike', "%{$termino}%")
                    ->orWhere('Apellido', 'ilike', "%{$termino}%")
                    ->orWhere('Nombre_usuario', 'ilike', "%{$termino}%")
                    ->orWhere('Cargo', 'ilike', "%{$termino}%"));
            })
            ->when($filtros['estado'] ?? null,
                fn ($query, $estado) => $query->where('Estado', $estado))
            ->orderBy('Apellido')
            ->paginate($filtros['tamano'] ?? 50)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Personal
    {
        $datos['Estado'] = $datos['Estado'] ?? 'Activo';

        return Personal::create($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Personal $personal, array $datos): Personal
    {
        $personal->update($datos);

        return $personal->fresh();
    }

    /**
     * Baja lógica: nunca se borra la fila (directorio histórico de asignaciones).
     */
    public function darDeBaja(Personal $personal): Personal
    {
        $asignacionesActivas = $personal->asignaciones()->activas()->count();

        if ($asignacionesActivas > 0) {
            throw new RuntimeException('No se puede dar de baja: la persona tiene equipos asignados activamente.');
        }

        $personal->update(['Estado' => 'Inactivo']);

        return $personal->fresh();
    }

    /**
     * @return Collection<int, Asignacion>
     */
    public function equiposAsignados(Personal $personal): Collection
    {
        return $personal->asignaciones()
            ->activas()
            ->with('activo.estadoCatalogo')
            ->get();
    }
}
