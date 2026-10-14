<?php

namespace App\Services;

use App\Models\Ubicacion;
use App\Services\Concerns\BusquedaAproximada;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UbicacionService
{
    use BusquedaAproximada;
    use PaginacionAcotada;

    /**
     * Consulta base del listado de ubicaciones con filtros (sin paginar).
     *
     * @param  array{termino?: string, estado?: string}  $filtros
     * @return Builder<Ubicacion>
     */
    public function consultaListado(array $filtros): Builder
    {
        return Ubicacion::query()
            ->withCount('activos')
            ->when($filtros['termino'] ?? null, fn ($query, $termino) => $this->aplicarBusqueda(
                $query,
                ['Nombre', 'Descripcion'],
                $termino,
            ))
            ->when($filtros['estado'] ?? null,
                fn ($query, $estado) => $query->where('Estado', $estado))
            ->orderBy('Nombre')
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
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Ubicacion
    {
        $datos['Estado'] = $datos['Estado'] ?? 'Activo';

        return Ubicacion::create($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Ubicacion $ubicacion, array $datos): Ubicacion
    {
        $ubicacion->update($datos);

        return $ubicacion->fresh();
    }

    /**
     * Baja lógica: nunca se borra la fila para no perder la referencia de los
     * activos que la usaron. Los activos conservan su ubicación denormalizada.
     */
    public function darDeBaja(Ubicacion $ubicacion): Ubicacion
    {
        $ubicacion->update(['Estado' => 'Inactivo']);

        return $ubicacion->fresh();
    }
}
