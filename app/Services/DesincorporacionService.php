<?php

namespace App\Services;

use App\Enums\TipoDesincorporacion;
use App\Models\Activo;
use App\Models\Desincorporacion;
use App\Services\Concerns\BusquedaAproximada;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Consultas del apartado de equipos desincorporados. Lista los activos que
 * están en el grupo «Desincorporado» y enlaza, cuando existe, el registro de
 * baja (`DESINCORPORACIONES`) con su tipo y destino.
 */
class DesincorporacionService
{
    use BusquedaAproximada;
    use PaginacionAcotada;

    public function __construct(private readonly HistorialService $historial) {}

    /**
     * Consulta base del listado de desincorporados con filtros (sin paginar).
     *
     * @param  array{termino?: string, tipo?: string, desde?: string, hasta?: string, sin_datos?: mixed}  $filtros
     * @return Builder<Activo>
     */
    public function consultaListado(array $filtros): Builder
    {
        return Activo::query()
            ->select('ACTIVOS.*')
            ->addSelect([
                // Fecha de la baja más reciente, para ordenar el listado y el
                // reporte por antigüedad de la desincorporación.
                'desincorporado_el' => Desincorporacion::query()
                    ->select('Fecha')
                    ->whereColumn('fk_activo', 'ACTIVOS.id')
                    ->orderByDesc('Fecha')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->with(['estadoCatalogo', 'desincorporacion.usuario'])
            ->where(function (Builder $query) {
                // Es desincorporado si el grupo denormalizado lo indica o si el
                // estado de catálogo pertenece a ese grupo (activos importados).
                $query->where('Estado', 'Desincorporado')
                    ->orWhereHas('estadoCatalogo', fn (Builder $estado) => $estado->where('Grupo', 'Desincorporado'));
            })
            ->when($filtros['termino'] ?? null, fn (Builder $query, string $termino) => $this->aplicarBusqueda(
                $query,
                ['Nombre_de_activo', 'Serial', 'Etiqueta_activo', 'Modelo'],
                $termino,
            ))
            ->when($filtros['tipo'] ?? null, fn (Builder $query, string $tipo) => $query->whereHas(
                'desincorporacion',
                fn (Builder $desinc) => $desinc->where('Tipo', $tipo),
            ))
            ->when(! empty($filtros['sin_datos']), fn (Builder $query) => $query->whereDoesntHave('desincorporacion'))
            ->when($this->hayRangoFechas($filtros), function (Builder $query) use ($filtros): void {
                $query->whereHas('desincorporacion', function (Builder $desinc) use ($filtros): void {
                    $this->aplicarRangoFechas($desinc, 'Fecha', $filtros['desde'] ?? null, $filtros['hasta'] ?? null);
                });
            })
            ->orderByDesc('desincorporado_el')
            ->orderByDesc('ACTIVOS.id');
    }

    /**
     * @param  array{termino?: string, tipo?: string, desde?: string, hasta?: string, sin_datos?: mixed, tamano?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return $this->consultaListado($filtros)
            ->paginate($this->tamanoPagina($filtros['tamano'] ?? null))
            ->withQueryString();
    }

    /**
     * Edición posterior de los datos de una baja vigente. Se permite corregir
     * tipo, destino, motivo y fecha; no se borra el registro.
     *
     * @param  array{tipo: string, destinatario: string, detalle_destino?: string|null, referencia?: string|null, monto?: float|int|string|null, moneda?: string|null, motivo: string, fecha: string}  $datos
     */
    public function actualizar(Desincorporacion $desincorporacion, array $datos, int $usuarioId): Desincorporacion
    {
        if ($desincorporacion->Estado !== 'Vigente') {
            throw new RuntimeException('No se puede editar una desincorporación anulada.');
        }

        return DB::transaction(function () use ($desincorporacion, $datos, $usuarioId): Desincorporacion {
            $moneda = $datos['moneda'] ?? null;

            if (TipoDesincorporacion::tryFrom($datos['tipo']) === TipoDesincorporacion::Venta && blank($moneda)) {
                $moneda = 'USD';
            }

            $desincorporacion->update([
                'Tipo' => $datos['tipo'],
                'Destinatario' => $datos['destinatario'],
                'Detalle_destino' => $datos['detalle_destino'] ?? null,
                'Referencia' => $datos['referencia'] ?? null,
                'Monto' => $datos['monto'] ?? null,
                'Moneda' => $moneda,
                'Motivo' => $datos['motivo'],
                'Fecha' => $datos['fecha'],
            ]);

            $this->historial->registrar(
                $desincorporacion->fk_activo,
                'Modificacion',
                'Datos de la desincorporación modificados',
                usuarioId: $usuarioId,
            );

            return $desincorporacion->fresh('usuario');
        });
    }

    /**
     * @param  array{desde?: string, hasta?: string}  $filtros
     */
    private function hayRangoFechas(array $filtros): bool
    {
        return $this->rangoValido($filtros['desde'] ?? null)
            || $this->rangoValido($filtros['hasta'] ?? null);
    }

    private function rangoValido(mixed $fecha): bool
    {
        return is_string($fecha) && trim($fecha) !== '';
    }
}
