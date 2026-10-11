<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\HistorialEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HistorialService
{
    /**
     * Consulta base del historial de asignaciones con filtros (sin paginar).
     *
     * @param  array{desde?: string|null, hasta?: string|null, personal_id?: int|string|null}  $filtros
     * @return Builder<Asignacion>
     */
    public function consultaAsignaciones(array $filtros): Builder
    {
        return Asignacion::query()
            ->with(['activo:id,Nombre_de_activo,Etiqueta_activo,Estado', 'personal:id,Nombre,Apellido', 'usuario:id,name'])
            ->when($filtros['desde'] ?? null,
                fn ($query, $desde) => $query->whereDate('Fecha_asignacion', '>=', $desde))
            ->when($filtros['hasta'] ?? null,
                fn ($query, $hasta) => $query->whereDate('Fecha_asignacion', '<=', $hasta))
            ->when($filtros['personal_id'] ?? null,
                fn ($query, $personalId) => $query->where('fk_Personal', $personalId))
            ->orderByDesc('Fecha_asignacion')
            ->orderByDesc('id');
    }

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
