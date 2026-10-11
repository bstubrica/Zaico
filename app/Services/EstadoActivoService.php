<?php

namespace App\Services;

use App\Models\EstadoActivo;

class EstadoActivoService
{
    /**
     * @param  array{Nombre: string, Grupo: string, Deployed?: bool|string, Deployable?: bool|string, Estado?: bool|string}  $datos
     */
    public function crear(array $datos): EstadoActivo
    {
        return EstadoActivo::create([
            'Nombre' => $datos['Nombre'],
            'Grupo' => $datos['Grupo'],
            'Deployed' => $this->booleano($datos['Deployed'] ?? false),
            'Deployable' => $this->booleano($datos['Deployable'] ?? false),
            'Estado' => $this->booleano($datos['Estado'] ?? true),
        ]);
    }

    /**
     * @param  array{Nombre: string, Grupo: string, Deployed?: bool|string, Deployable?: bool|string, Estado?: bool|string}  $datos
     */
    public function actualizar(EstadoActivo $estado, array $datos): EstadoActivo
    {
        $estado->update([
            'Nombre' => $datos['Nombre'],
            'Grupo' => $datos['Grupo'],
            'Deployed' => $this->booleano($datos['Deployed'] ?? $estado->Deployed),
            'Deployable' => $this->booleano($datos['Deployable'] ?? $estado->Deployable),
            'Estado' => $this->booleano($datos['Estado'] ?? $estado->Estado),
        ]);

        return $estado;
    }

    /**
     * El estado no se elimina si está en uso por activos: hacerlo dejaría
     * huérfanos los registros (fk_estado con borrado nulo).
     */
    public function eliminar(EstadoActivo $estado): bool
    {
        $enUso = $estado->activos()->count();

        abort_if(
            $enUso > 0,
            409,
            "No se puede eliminar: {$enUso} equipo(s) usan este estado."
        );

        return (bool) $estado->delete();
    }

    private function booleano(bool|string|int|null $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
