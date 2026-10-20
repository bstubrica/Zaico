<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Alerta;
use App\Models\Compra;
use App\Models\HistorialEvento;
use App\Models\User;
use App\Notifications\AlertaInventario;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Detecta activos que requieren atención y genera alertas (Fase 8):
 *
 * - Reparación prolongada: activos en grupo `Reparacion` que llevan más de
 *   `zaico.alertas.reparacion_dias` días en ese estado.
 * - Garantía por vencer: activos cuya garantía vence dentro de los próximos
 *   `zaico.alertas.garantia_dias` días.
 *
 * La fecha de vencimiento se toma del campo `COMPRAS.Garantia` si contiene una
 * fecha; si no, se calcula como `Fecha_Compra` + duración de `Tiempo_garantia`
 * (p. ej. "12 meses", "1 año", "36").
 */
class AlertaService
{
    /**
     * Listado paginado de alertas activas, opcionalmente filtrado por tipo.
     */
    public function listar(?string $tipo = null, int $tamano = 50): LengthAwarePaginator
    {
        return Alerta::query()
            ->activas()
            ->with('activo')
            ->when($tipo, fn ($query, $tipo) => $query->where('Tipo', $tipo))
            ->orderBy('Tipo')
            ->orderBy('Fecha_referencia')
            ->paginate($tamano)
            ->withQueryString();
    }

    /**
     * Cantidad de alertas activas por tipo.
     *
     * @return Collection<string, int>
     */
    public function resumenPorTipo(): Collection
    {
        return Alerta::query()
            ->activas()
            ->select('Tipo')
            ->selectRaw('count(*) as total')
            ->groupBy('Tipo')
            ->pluck('total', 'Tipo');
    }

    /**
     * @return array{activas: int, nuevas: int, resueltas: int}
     */
    public function generar(): array
    {
        $esperadas = [
            ...$this->alertasReparacionProlongada(),
            ...$this->alertasGarantiaPorVencer(),
        ];

        $nuevas = new Collection;

        foreach ($esperadas as $datos) {
            $alerta = Alerta::updateOrCreate(
                ['Tipo' => $datos['Tipo'], 'fk_activo' => $datos['fk_activo']],
                [
                    'Mensaje' => $datos['Mensaje'],
                    'Fecha_referencia' => $datos['Fecha_referencia'],
                    'Estado' => 'Activa',
                ],
            );

            if ($alerta->wasRecentlyCreated || $alerta->wasChanged('Estado')) {
                $nuevas->push($alerta);
            }
        }

        $resueltas = $this->resolverObsoletas(array_keys($esperadas));

        if ($nuevas->isNotEmpty()) {
            $this->notificar($nuevas);
        }

        return [
            'activas' => count($esperadas),
            'nuevas' => $nuevas->count(),
            'resueltas' => $resueltas,
        ];
    }

    /**
     * @return array<string, array{Tipo: string, fk_activo: int, Mensaje: string, Fecha_referencia: string}>
     */
    private function alertasReparacionProlongada(): array
    {
        $dias = (int) config('zaico.alertas.reparacion_dias', 15);
        $limite = now()->subDays($dias)->startOfDay();

        $activos = Activo::query()->where('Estado', 'Reparacion')->get();

        if ($activos->isEmpty()) {
            return [];
        }

        // Última entrada al estado por activo, en una sola consulta.
        $ultimoCambio = HistorialEvento::query()
            ->whereIn('fk_activo', $activos->pluck('id'))
            ->where('Tipo_evento', 'CambioEstado')
            ->selectRaw('fk_activo, MAX("Fecha") AS ultima')
            ->groupBy('fk_activo')
            ->pluck('ultima', 'fk_activo');

        $alertas = [];

        foreach ($activos as $activo) {
            $referencia = $this->fechaEntradaEstado($activo, $ultimoCambio->get($activo->id));

            if ($referencia === null || $referencia->greaterThan($limite)) {
                continue;
            }

            $alertas[Alerta::TIPO_REPARACION.':'.$activo->id] = [
                'Tipo' => Alerta::TIPO_REPARACION,
                'fk_activo' => $activo->id,
                'Mensaje' => "«{$activo->Etiqueta_activo}» lleva en reparación desde el {$referencia->format('d/m/Y')}.",
                'Fecha_referencia' => $referencia->toDateString(),
            ];
        }

        return $alertas;
    }

    /**
     * @return array<string, array{Tipo: string, fk_activo: int, Mensaje: string, Fecha_referencia: string}>
     */
    private function alertasGarantiaPorVencer(): array
    {
        $dias = (int) config('zaico.alertas.garantia_dias', 30);
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays($dias);
        $alertas = [];

        // Prefiltro en SQL: la garantía calculada nunca vence antes de la
        // compra, así que una compra posterior al horizonte no puede caer en
        // la ventana; así no se cargan en memoria las COMPRAS fuera de rango.
        // `lazy` procesa por bloques de 500 (con eager load por bloque).
        Compra::query()
            ->whereNotNull('Fecha_Compra')
            ->where('Fecha_Compra', '<=', $limite->toDateString())
            ->with('activo')
            ->lazy(500)
            ->each(function (Compra $compra) use (&$alertas, $hoy, $limite): void {
                $vence = $this->vencimientoGarantia($compra);

                if ($vence === null || $compra->activo === null) {
                    return;
                }

                if ($vence->lessThan($hoy) || $vence->greaterThan($limite)) {
                    return;
                }

                $alertas[Alerta::TIPO_GARANTIA.':'.$compra->fk_activo] = [
                    'Tipo' => Alerta::TIPO_GARANTIA,
                    'fk_activo' => $compra->fk_activo,
                    'Mensaje' => "La garantía de «{$compra->activo->Etiqueta_activo}» vence el {$vence->format('d/m/Y')}.",
                    'Fecha_referencia' => $vence->toDateString(),
                ];
            });

        return $alertas;
    }

    private function resolverObsoletas(array $clavesVigentes): int
    {
        $ids = Alerta::query()
            ->whereIn('Tipo', [Alerta::TIPO_REPARACION, Alerta::TIPO_GARANTIA])
            ->where('Estado', 'Activa')
            ->get(['id', 'Tipo', 'fk_activo'])
            ->reject(fn (Alerta $alerta) => in_array($alerta->Tipo.':'.$alerta->fk_activo, $clavesVigentes, true))
            ->pluck('id')
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return Alerta::query()->whereIn('id', $ids->all())->update(['Estado' => 'Resuelta']);
    }

    private function notificar(Collection $nuevas): void
    {
        $destinatarios = User::query()
            ->whereIn('role', config('zaico.alertas.roles', ['Admin', 'SoporteTecnico']))
            ->get();

        if ($destinatarios->isEmpty()) {
            return;
        }

        foreach ($nuevas as $alerta) {
            $alerta->loadMissing('activo');
            Notification::send($destinatarios, new AlertaInventario($alerta));
        }
    }

    private function fechaEntradaEstado(Activo $activo, ?string $ultimoCambio = null): ?Carbon
    {
        if ($ultimoCambio !== null) {
            return Carbon::parse($ultimoCambio);
        }

        return $activo->Actualizado_el ?? $activo->Creado_el;
    }

    private function vencimientoGarantia(Compra $compra): ?Carbon
    {
        $explicita = $this->fechaExplicita($compra->Garantia);

        if ($explicita !== null) {
            return $explicita;
        }

        if ($compra->Fecha_Compra === null) {
            return null;
        }

        return $this->sumarDuracion($compra->Fecha_Compra->copy(), (string) $compra->Tiempo_garantia);
    }

    private function fechaExplicita(?string $valor): ?Carbon
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat('!'.$formato, $valor);
            } catch (\Throwable) {
                continue;
            }

            if ($fecha !== false && $fecha->format($formato) === $valor) {
                return $fecha;
            }
        }

        return null;
    }

    private function sumarDuracion(Carbon $base, string $texto): ?Carbon
    {
        $texto = mb_strtolower(trim($texto));

        if ($texto === '' || preg_match('/(\d+(?:[.,]\d+)?)/', $texto, $coincidencia) !== 1) {
            return null;
        }

        $cantidad = (int) round((float) str_replace(',', '.', $coincidencia[1]));

        if ($cantidad <= 0) {
            return null;
        }

        if (str_contains($texto, 'año') || str_contains($texto, 'ano') || str_contains($texto, 'year')) {
            return $base->addYears($cantidad);
        }

        if (str_contains($texto, 'día') || str_contains($texto, 'dia') || str_contains($texto, 'day')) {
            return $base->addDays($cantidad);
        }

        return $base->addMonths($cantidad);
    }
}
