<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EstatusServidor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Reglas, mensajes y atributos de los datos de servidor y sus tarjetas de red.
 * Se comparten entre el alta y la edición del activo para que ambas rutas no
 * diverjan, igual que `DesincorporacionRules`.
 *
 * Todos los campos son opcionales: los servidores heredados del inventario
 * histórico no los tienen y el CSV de Snipec IT no los trae.
 */
trait ReglasServidor
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasServidor(): array
    {
        return [
            'IP' => ['nullable', 'string', 'ip', 'max:45'],
            'Sistema_Operativo' => ['nullable', 'string', 'max:100'],
            'Memoria_RAM' => ['nullable', 'string', 'max:100'],
            'Capacidad_Disco' => ['nullable', 'string', 'max:100'],
            'Estatus_Servidor' => ['nullable', Rule::in(EstatusServidor::valores())],
            'tarjetas_presente' => ['sometimes', 'boolean'],
            'tarjetas' => ['nullable', 'array', 'max:'.$this->maximoTarjetas()],
            'tarjetas.*.Numero' => ['required', 'integer', 'min:1', 'max:999', 'distinct'],
            'tarjetas.*.Nombre' => ['nullable', 'string', 'max:60'],
            'tarjetas.*.Direccion_MAC' => ['nullable', 'string', 'mac_address', 'max:17'],
            'tarjetas.*.IP' => ['nullable', 'string', 'ip', 'max:45'],
            'tarjetas.*.fk_switch' => ['nullable', Rule::exists('ACTIVOS', 'id')],
            'tarjetas.*.Puerto' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesServidor(): array
    {
        return [
            'IP.ip' => 'La IP del servidor no es válida',
            'IP.max' => 'La IP no puede exceder 45 caracteres',
            'Sistema_Operativo.max' => 'El sistema operativo no puede exceder 100 caracteres',
            'Memoria_RAM.max' => 'La memoria RAM no puede exceder 100 caracteres',
            'Capacidad_Disco.max' => 'La capacidad de disco no puede exceder 100 caracteres',
            'Estatus_Servidor.in' => 'El estatus del servidor no es válido',
            'tarjetas.max' => 'Solo se permiten :max tarjetas de red por equipo',
            'tarjetas.*.Numero.required' => 'Cada tarjeta necesita su número',
            'tarjetas.*.Numero.integer' => 'El número de tarjeta debe ser un entero',
            'tarjetas.*.Numero.min' => 'El número de tarjeta debe ser mayor que cero',
            'tarjetas.*.Numero.distinct' => 'Los números de tarjeta no pueden repetirse',
            'tarjetas.*.Nombre.max' => 'El nombre de la interfaz no puede exceder 60 caracteres',
            'tarjetas.*.Direccion_MAC.mac_address' => 'La MAC de la tarjeta no es válida',
            'tarjetas.*.IP.ip' => 'La IP de la tarjeta no es válida',
            'tarjetas.*.fk_switch.exists' => 'El switch seleccionado no existe en el inventario',
            'tarjetas.*.Puerto.max' => 'El puerto no puede exceder 30 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosServidor(): array
    {
        return [
            'IP' => 'IP',
            'Sistema_Operativo' => 'sistema operativo',
            'Memoria_RAM' => 'memoria RAM',
            'Capacidad_Disco' => 'capacidad de disco',
            'Estatus_Servidor' => 'estatus del servidor',
            'tarjetas' => 'tarjetas de red',
            'tarjetas.*.Numero' => 'número de tarjeta',
            'tarjetas.*.Nombre' => 'nombre de la interfaz',
            'tarjetas.*.Direccion_MAC' => 'MAC de la tarjeta',
            'tarjetas.*.IP' => 'IP de la tarjeta',
            'tarjetas.*.fk_switch' => 'switch',
            'tarjetas.*.Puerto' => 'puerto',
        ];
    }

    /**
     * Limpia los datos antes de validar: recorta espacios, convierte cadenas
     * vacías en `null` y descarta las filas de tarjeta completamente vacías
     * (las que el formulario dinámico deja al agregar y no completar).
     */
    protected function normalizarDatosServidor(): void
    {
        foreach (['IP', 'Sistema_Operativo', 'Memoria_RAM', 'Capacidad_Disco'] as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor)) {
                $valor = trim($valor);
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }

        $estatus = $this->input('Estatus_Servidor');

        if (is_string($estatus)) {
            $estatus = trim($estatus);
            $this->merge(['Estatus_Servidor' => $estatus === '' ? null : $estatus]);
        }

        if (! $this->has('tarjetas')) {
            return;
        }

        $tarjetas = $this->input('tarjetas');

        if (! is_array($tarjetas)) {
            $this->merge(['tarjetas' => []]);

            return;
        }

        $limpias = [];

        foreach ($tarjetas as $tarjeta) {
            if (! is_array($tarjeta)) {
                continue;
            }

            $tarjeta = array_map(
                fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor,
                $tarjeta,
            );

            $switch = $tarjeta['fk_switch'] ?? null;

            if ($switch === '' || $switch === '0') {
                $tarjeta['fk_switch'] = null;
            }

            $tieneDatos = array_filter($tarjeta, fn (mixed $valor): bool => filled($valor)) !== [];

            if (! $tieneDatos) {
                continue;
            }

            $limpias[] = $tarjeta;
        }

        $this->merge(['tarjetas' => array_values($limpias)]);
    }

    protected function maximoTarjetas(): int
    {
        return (int) config('zaico.servidores.maximo_tarjetas', 8);
    }
}
