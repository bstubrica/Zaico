<?php

namespace App\Enums;

/**
 * Motivo de negocio de la baja de un activo. Se guarda en
 * `DESINCORPORACIONES.Tipo` (valor corto sin acentos) y se muestra con su
 * etiqueta legible. Catálogo fijo: la validación se apoya en esta lista para
 * no depender de una tabla de catálogo adicional.
 */
enum TipoDesincorporacion: string
{
    case Venta = 'Venta';
    case Donacion = 'Donacion';
    case Transferencia = 'Transferencia';
    case Desecho = 'Desecho';
    case Robo = 'Robo';
    case Otra = 'Otra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Donacion => 'Donación',
            self::Transferencia => 'Transferencia',
            self::Desecho => 'Desecho',
            self::Robo => 'Robo o pérdida',
            self::Otra => 'Otra',
        };
    }

    /**
     * Determina si el tipo exige registrar un monto (una venta siempre tiene
     * un valor económico asociado).
     */
    public function exigeMonto(): bool
    {
        return $this === self::Venta;
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }

    /**
     * Opciones para `<select>`: valor almacenado => etiqueta visible.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $tipo) {
            $opciones[$tipo->value] = $tipo->etiqueta();
        }

        return $opciones;
    }

    /**
     * Etiqueta legible a partir del valor almacenado, tolerante a valores
     * desconocidos (registros antiguos o importados).
     */
    public static function etiquetaDe(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return 'Sin clasificar';
        }

        return self::tryFrom($valor)?->etiqueta() ?? $valor;
    }
}
