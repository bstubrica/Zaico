<?php

namespace App\Services\Concerns;

/**
 * Tope de registros por operación masiva: acota el trabajo de un solo lote
 * atómico y evita que una selección accidental (p. ej. «todos los resultados»)
 * bloquee la base de datos con una transacción desmedida.
 */
trait LimiteLote
{
    public const MAXIMO_LOTE = 2000;
}
