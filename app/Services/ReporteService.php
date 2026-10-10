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
            fputcsv($salida, $cabeceras, self::DELIMITADOR);

            foreach ($consulta->lazy(500) as $registro) {
                fputcsv($salida, $mapear($registro), self::DELIMITADOR);
            }

            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
