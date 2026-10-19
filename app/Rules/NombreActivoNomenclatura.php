<?php

namespace App\Rules;

use App\Enums\CategoriaActivo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Valida que `Nombre_de_activo` respete estrictamente la estructura de nomenclatura
 * `Tipo Fabricante Modelo [Serial] Etiqueta` (ver `Nomenclatura.md`).
 *
 * El nombre debe coincidir exactamente con la concatenación de los componentes requeridos:
 * - Tipo = categoría canónica.
 * - Fabricante.
 * - Modelo (efectivo: catálogo o texto libre).
 * - Serial: obligatorio en el nombre si el campo Serial está presente.
 * - Etiqueta.
 *
 * Se tolera normalización de espacios múltiples y extremos en blanco, pero la
 * composición debe ser idéntica y sin elementos ajenos a los componentes.
 */
class NombreActivoNomenclatura implements ValidationRule
{
    public function __construct(
        private readonly ?string $categoria,
        private readonly ?string $fabricante,
        private readonly ?string $modelo,
        private readonly ?string $etiqueta,
        private readonly ?string $serial = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $nombreNormalizado = $this->limpiarEspacios($value);
        $esperado = $this->nombreEsperado();

        if ($esperado === null) {
            return;
        }

        if ($this->limpiarEspacios($esperado) !== $nombreNormalizado) {
            $fail("El nombre del activo debe estructurarse exactamente según la nomenclatura: «{$esperado}».");
        }
    }

    /**
     * Construye el nombre exacto esperado según los componentes definidos en Nomenclatura.md.
     */
    public function nombreEsperado(): ?string
    {
        $tipo = CategoriaActivo::canonico($this->categoria) ?? trim((string) $this->categoria);
        $fabricante = trim((string) $this->fabricante);
        $modelo = trim((string) $this->modelo);
        $serial = trim((string) $this->serial);
        $etiqueta = trim((string) $this->etiqueta);

        if ($tipo === '' || $fabricante === '' || $modelo === '' || $etiqueta === '') {
            return null;
        }

        $partes = [$tipo, $fabricante, $modelo];

        if ($serial !== '') {
            $partes[] = $serial;
        }

        $partes[] = $etiqueta;

        return implode(' ', $partes);
    }

    private function limpiarEspacios(string $valor): string
    {
        return preg_replace('/\s+/', ' ', trim($valor)) ?? trim($valor);
    }
}
