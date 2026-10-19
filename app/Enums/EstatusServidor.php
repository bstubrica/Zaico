<?php

namespace App\Enums;

/**
 * Estatus físico de un servidor (encendido, apagado o en mantenimiento). Es un
 * dato operativo independiente de `ACTIVOS.Estado`, que refleja el grupo del
 * catálogo de inventario (Asignado, Disponible, Desincorporado…).
 */
enum EstatusServidor: string
{
    case Encendido = 'Encendido';
    case Apagado = 'Apagado';
    case Mantenimiento = 'Mantenimiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Encendido => 'Encendido',
            self::Apagado => 'Apagado',
            self::Mantenimiento => 'En mantenimiento',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $estatus): string => $estatus->value, self::cases());
    }

    /**
     * Opciones para `<select>`: valor almacenado => etiqueta visible.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $estatus) {
            $opciones[$estatus->value] = $estatus->etiqueta();
        }

        return $opciones;
    }

    /**
     * Etiqueta legible a partir del valor almacenado, tolerante a valores
     * desconocidos (datos heredados o editados por fuera del formulario).
     */
    public static function etiquetaDe(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return self::tryFrom($valor)?->etiqueta() ?? $valor;
    }
}
