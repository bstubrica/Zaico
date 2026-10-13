<?php

namespace App\Services\Concerns;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda por texto y filtrado por rango de fechas portables entre los
 * motores soportados (PostgreSQL en producción, SQLite en desarrollo local).
 *
 * `whereLike` compila a `ilike` en PostgreSQL y a `like` en SQLite, por lo que
 * la búsqueda es insensible a mayúsculas en ambos. El término se divide por
 * espacios: cada palabra debe aparecer (AND) en al menos una de las columnas
 * indicadas (OR), de modo que «celular samsung» encuentre «Samsung Galaxy
 * celular 2024» y «carlos paez» encuentre a la persona «Carlos Páez».
 */
trait BusquedaAproximada
{
    /**
     * @param  Builder<*>  $query
     * @param  array<int, string|Expression>  $columnas
     */
    protected function aplicarBusqueda(Builder $query, array $columnas, mixed $termino): void
    {
        if (! is_string($termino)) {
            return;
        }

        $termino = trim($termino);

        if ($termino === '') {
            return;
        }

        $palabras = preg_split('/\s+/', $termino, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($palabras as $palabra) {
            $query->where(function (Builder $grupo) use ($columnas, $palabra) {
                foreach ($columnas as $indice => $columna) {
                    $grupo->{$indice === 0 ? 'whereLike' : 'orWhereLike'}($columna, "%{$palabra}%");
                }
            });
        }
    }

    /**
     * Aplica `whereDate` solo con fechas válidas (YYYY-MM-DD); ignora cualquier
     * otro valor para no romper la consulta con entradas malformadas.
     *
     * @param  Builder<*>  $query
     */
    protected function aplicarRangoFechas(Builder $query, string $columna, mixed $desde, mixed $hasta): void
    {
        if ($this->esFechaValida($desde)) {
            $query->whereDate($columna, '>=', trim((string) $desde));
        }

        if ($this->esFechaValida($hasta)) {
            $query->whereDate($columna, '<=', trim((string) $hasta));
        }
    }

    private function esFechaValida(mixed $fecha): bool
    {
        if (! is_string($fecha) || trim($fecha) === '') {
            return false;
        }

        $fecha = trim($fecha);

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            return false;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }
}
