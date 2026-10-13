<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Personal;
use App\Services\Concerns\BusquedaAproximada;
use App\Services\Concerns\LimiteLote;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PersonalService
{
    use BusquedaAproximada;
    use LimiteLote;
    use PaginacionAcotada;

    /**
     * Consulta base del listado de personal con filtros (sin paginar).
     *
     * @param  array{termino?: string, estado?: string}  $filtros
     * @return Builder<Personal>
     */
    public function consultaListado(array $filtros): Builder
    {
        return Personal::query()
            ->withCount(['asignaciones as asignaciones_activas' => fn ($query) => $query->activas()])
            ->when($filtros['termino'] ?? null, fn ($query, $termino) => $this->aplicarBusqueda(
                $query,
                ['Nombre', 'Apellido', 'Nombre_usuario', 'Cargo'],
                $termino,
            ))
            ->when($filtros['estado'] ?? null,
                fn ($query, $estado) => $query->where('Estado', $estado))
            ->orderBy('Apellido')
            ->orderBy('id');
    }

    /**
     * @param  array{termino?: string, estado?: string, tamano?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return $this->consultaListado($filtros)
            ->paginate($this->tamanoPagina($filtros['tamano'] ?? null))
            ->withQueryString();
    }

    /**
     * IDs de todas las personas que coinciden con el filtro activo, para la
     * opción «seleccionar todos los resultados» del listado.
     *
     * @param  array{termino?: string, estado?: string}  $filtros
     * @return array<int, int>
     */
    public function idsDeFiltro(array $filtros): array
    {
        return $this->consultaListado($filtros)->pluck('id')->all();
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
     * Baja lógica masiva, atómica: o se inactivan todas las personas
     * procesables o no se aplica ninguna. Las personas con equipos asignados
     * activamente se excluyen antes de la transacción y se detallan.
     *
     * @param  array<int, int>  $ids
     * @return array{inactivados: int, omitidos: array<int, array{label: string, motivo: string}>}
     */
    public function darDeBajaLote(array $ids): array
    {
        $personas = Personal::query()
            ->withCount(['asignaciones as asignaciones_activas' => fn ($query) => $query->activas()])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $procesables = [];
        $omitidos = [];

        foreach ($ids as $id) {
            $persona = $personas->get($id);

            if ($persona === null) {
                $omitidos[] = ['label' => "#{$id}", 'motivo' => 'No se encontró en el directorio.'];

                continue;
            }

            if ($persona->asignaciones_activas > 0) {
                $omitidos[] = ['label' => $persona->NombreCompleto, 'motivo' => 'Tiene equipos asignados activamente.'];

                continue;
            }

            $procesables[] = $persona;
        }

        DB::transaction(function () use ($procesables): void {
            foreach ($procesables as $persona) {
                $persona->update(['Estado' => 'Inactivo']);
            }
        });

        return ['inactivados' => count($procesables), 'omitidos' => $omitidos];
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
