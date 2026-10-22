<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reglas, mensajes y normalización de los datos de red del equipo que **no**
 * son exclusivos de los servidores (hoy, la dirección IP).
 *
 * La IP es un campo general y opcional de `ACTIVOS`, igual que `Direccion_MAC`:
 * cualquier categoría de equipo puede registrarla. Se comparte entre el alta y
 * la edición para que ambas rutas no diverjan, igual que `ReglasServidor` y
 * `ReglasNomenclatura`.
 */
trait ReglasRed
{
    /**
     * La IP es opcional en todos los equipos. Se prioriza IPv4 (formato de la
     * red interna) y se acepta también IPv6; `max:45` es el largo máximo de
     * una IPv6 completa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasRed(): array
    {
        return [
            'IP' => ['nullable', 'string', 'ip', 'max:45'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesRed(): array
    {
        return [
            'IP.ip' => 'La dirección IP no es válida (IPv4 o IPv6)',
            'IP.max' => 'La IP no puede exceder 45 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosRed(): array
    {
        return [
            'IP' => 'dirección IP',
        ];
    }

    /**
     * Limpia la IP antes de validar: recorta espacios y convierte la cadena
     * vacía en `null` (un equipo puede no tener IP registrada).
     */
    protected function normalizarRed(): void
    {
        $ip = $this->input('IP');

        if (! is_string($ip)) {
            return;
        }

        $ip = trim($ip);

        $this->merge(['IP' => $ip === '' ? null : $ip]);
    }
}
