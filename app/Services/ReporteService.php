<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteService
{
    private const DELIMITADOR = ';';

    /**
     * Genera una descarga CSV en streaming a partir de una consulta Eloquent.
     *
     * Se escribe BOM UTF-8 para compatibilidad con Excel y se procesa la
     * consulta en bloques (lazy) para no cargar todo el resultado en memoria.
     *
     * @param  array<int, string>  $cabeceras
     * @param  Builder<*>  $consulta
     * @param  callable(mixed): array<int, mixed>  $mapear
     */
    public function exportar(string $nombreArchivo, array $cabeceras, Builder $consulta, callable $mapear): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabeceras, $consulta, $mapear) {
            $salida = fopen('php://output', 'w');

            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, array_map($this->sanearCelda(...), $cabeceras), self::DELIMITADOR);

            foreach ($consulta->lazy(500) as $registro) {
                fputcsv($salida, array_map($this->sanearCelda(...), $mapear($registro)), self::DELIMITADOR);
            }

            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Neutraliza la inyección de fórmulas (CSV injection): Excel/LibreOffice
     * ejecutan celdas que empiezan por `=`, `+`, `-`, `@`, tabulador o CR.
     * Se antepone un apóstrofo (forzando texto) salvo en valores numéricos.
     */
    private function sanearCelda(mixed $valor): mixed
    {
        if ($valor === null || is_int($valor) || is_float($valor)) {
            return $valor;
        }

        $texto = (string) $valor;

        if ($texto !== '' && ! is_numeric($texto) && preg_match('/^[=+\-@\t\r]/', $texto) === 1) {
            return "'".$texto;
        }

        return $texto;
    }
}
