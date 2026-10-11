<?php

namespace App\Services\Concerns;

/**
 * Acota el tamaño de página de los listados para evitar consultas abusivas
 * (p. ej. `?tamano=999999`) que agoten memoria o tiempo de respuesta.
 */
trait PaginacionAcotada
{
    private const TAMANO_POR_PAGINA = 50;

    private const TAMANO_MAXIMO = 100;

    protected function tamanoPagina(int|string|null $tamano): int
    {
        return max(1, min(self::TAMANO_MAXIMO, (int) ($tamano ?? self::TAMANO_POR_PAGINA)));
    }
}
