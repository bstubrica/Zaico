<?php

namespace App\Enums;

/**
 * Formatos de salida de la carta de desincorporación. `Impresion` devuelve una
 * vista HTML lista para imprimir (o guardar como PDF desde el navegador); el
 * resto son descargas generadas en el servidor.
 */
enum FormatoCarta: string
{
    case Impresion = 'impresion';
    case Pdf = 'pdf';
    case Xls = 'xls';
    case Xlsx = 'xlsx';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Impresion => 'Ver / Imprimir',
            self::Pdf => 'PDF',
            self::Xls => 'Excel (.xls)',
            self::Xlsx => 'Excel (.xlsx)',
        };
    }

    public function mime(): string
    {
        return match ($this) {
            self::Impresion => 'text/html; charset=UTF-8',
            self::Pdf => 'application/pdf',
            self::Xls => 'application/vnd.ms-excel',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Impresion => 'html',
            self::Pdf => 'pdf',
            self::Xls => 'xls',
            self::Xlsx => 'xlsx',
        };
    }

    /**
     * Indica si el formato es una descarga (con `Content-Disposition`) o una
     * vista que el navegador debe mostrar en pantalla.
     */
    public function esDescarga(): bool
    {
        return $this !== self::Impresion;
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $formato): string => $formato->value, self::cases());
    }

    /**
     * Opciones para `<select>`/menú: valor almacenado => etiqueta visible.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $formato) {
            $opciones[$formato->value] = $formato->etiqueta();
        }

        return $opciones;
    }
}
