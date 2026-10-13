<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\HistorialEvento;
use App\Services\Concerns\BusquedaAproximada;
use Illuminate\Database\Eloquent\Builder;

class HistorialService
{
    use BusquedaAproximada;

    /**
     * Consulta base del historial de asignaciones con filtros (sin paginar).
     *
     * @param  array{desde?: string|null, hasta?: string|null, personal_id?: int|string|null}  $filtros
     * @return Builder<Asignacion>
     */
    public function consultaAsignaciones(array $filtros): Builder
    {
        $query = Asignacion::query()
            ->with(['activo:id,Nombre_de_activo,Etiqueta_activo,Estado', 'personal:id,Nombre,Apellido', 'usuario:id,name']);

        $this->aplicarRangoFechas(
            $query,
            'Fecha_asignacion',
            $filtros['desde'] ?? null,
            $filtros['hasta'] ?? null,
        );

        $query->when($filtros['personal_id'] ?? null, function ($query, $personalId) {
            if (is_numeric($personalId) && (int) $personalId > 0) {
                $query->where('fk_Personal', (int) $personalId);
            }
        });

        return $query
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
}
