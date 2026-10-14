<?php

namespace App\Services;

use App\Models\ModeloActivo;
use App\Services\Concerns\BusquedaAproximada;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ModeloActivoService
{
    use BusquedaAproximada;
    use PaginacionAcotada;

    /**
     * Consulta base del listado de modelos con filtros (sin paginar).
     *
     * @param  array{termino?: string, estado?: string}  $filtros
     * @return Builder<ModeloActivo>
     */
    public function consultaListado(array $filtros): Builder
    {
        return ModeloActivo::query()
            ->withCount('activos')
            ->when($filtros['termino'] ?? null, fn ($query, $termino) => $this->aplicarBusqueda(
                $query,
                ['Nombre', 'Fabricante', 'Categoria'],
                $termino,
            ))
            ->when($filtros['estado'] ?? null,
                fn ($query, $estado) => $query->where('Estado', $estado))
            ->orderBy('Fabricante')
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
    public function crear(array $datos): ModeloActivo
    {
        $datos['Estado'] = $datos['Estado'] ?? 'Activo';

        return ModeloActivo::create($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(ModeloActivo $modelo, array $datos): ModeloActivo
    {
        $modelo->update($datos);

        return $modelo->fresh();
    }

    /**
     * Baja lógica: nunca se borra la fila para no perder la referencia de los
     * activos que la usaron. Los activos conservan su modelo denormalizado.
     */
    public function darDeBaja(ModeloActivo $modelo): ModeloActivo
    {
        $modelo->update(['Estado' => 'Inactivo']);

        return $modelo->fresh();
    }
}
