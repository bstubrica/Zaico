<?php

namespace App\Services;

use App\Models\EstadoActivo;
use Illuminate\Database\Eloquent\Collection;

class EstadoService
{
    /**
     * Catálogo activo (`Estado = 1`) con el conteo de activos que lo usan.
     *
     * @return Collection<int, EstadoActivo>
     */
    public function listar(): Collection
    {
        return EstadoActivo::query()
            ->withCount('activos')
            ->where('Estado', 1)
            ->orderBy('Grupo')
            ->orderBy('Nombre')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): EstadoActivo
    {
        $datos['Deployed'] = (bool) ($datos['Deployed'] ?? false);
        $datos['Deployable'] = (bool) ($datos['Deployable'] ?? false);
        $datos['Estado'] = 1;

        return EstadoActivo::create($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(EstadoActivo $estado, array $datos): EstadoActivo
    {
        $datos['Deployed'] = (bool) ($datos['Deployed'] ?? false);
        $datos['Deployable'] = (bool) ($datos['Deployable'] ?? false);

        $estado->update($datos);

        return $estado->fresh();
    }

    /**
     * Baja lógica: nunca se borra la fila para no perder la referencia de los
     * activos que apuntan con `fk_estado`. Se marca `Estado = 0`, con lo que
     * desaparece del catálogo y de los selectores, pero conserva su historial.
     */
    public function eliminar(EstadoActivo $estado): EstadoActivo
    {
        $estado->update(['Estado' => 0]);

        return $estado->fresh();
    }
}
