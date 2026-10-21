<?php

namespace App\Services\Concerns;

use App\Models\Compra;
use Carbon\Carbon;

/**
 * Cálculo del vencimiento de garantía de un equipo, usado por las alertas y
 * por los indicadores del módulo de reportes para que ambos coincidan:
 *
 * 1. Si `COMPRAS.Garantia` contiene una fecha explícita, manda esa.
 * 2. Si no, `Fecha_Compra` + duración de `Tiempo_garantia` ("12 meses",
 *    "1 año", "36"...).
 */
trait VencimientoGarantia
{
    protected function vencimientoGarantia(Compra $compra): ?Carbon
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

    protected function fechaExplicita(?string $valor): ?Carbon
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

    protected function sumarDuracion(Carbon $base, string $texto): ?Carbon
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
