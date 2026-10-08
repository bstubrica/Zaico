<?php

namespace App\Services;

use App\Models\HistorialEvento;
use Illuminate\Database\Eloquent\Collection;

class HistorialService
{
    /**
     * Registra un evento de auditoría para un activo.
     *
     * Tipos válidos: Creacion, Modificacion, Asignacion, Devolucion,
     * CambioEstado, Mantenimiento, Importacion.
     */
    public function registrar(
        int $activoId,
        string $tipo,
        string $descripcion,
        ?string $anterior = null,
        ?string $nuevo = null,
        ?int $usuarioId = null,
    ): HistorialEvento {
        return HistorialEvento::create([
            'fk_activo' => $activoId,
            'Tipo_evento' => $tipo,
            'Descripcion' => $descripcion,
            'Valor_anterior' => $anterior,
            'Valor_nuevo' => $nuevo,
            'fk_usuario' => $usuarioId,
            'Fecha' => now(),
        ]);
    }

    /**
     * @return Collection<int, HistorialEvento>
     */
    public function eventosDeActivo(int $activoId): Collection
    {
        return HistorialEvento::where('fk_activo', $activoId)
            ->with('usuario:id,name')
            ->orderByDesc('Fecha')
            ->get();
    }
}
