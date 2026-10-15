<?php

namespace App\Http\Requests;

use App\Enums\FormatoCarta;
use App\Enums\TipoDesincorporacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de salida y los filtros con los que se genera la carta de
 * desincorporación (individual o como acta del listado).
 */
class CartaDesincorporacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'formato' => ['nullable', Rule::enum(FormatoCarta::class)],
            'termino' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(TipoDesincorporacion::valores())],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'sin_datos' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Formato solicitado, con «Ver / Imprimir» como opción por defecto.
     */
    public function formato(): FormatoCarta
    {
        return FormatoCarta::tryFrom((string) $this->input('formato')) ?? FormatoCarta::Impresion;
    }

    /**
     * Filtros del listado aplicables a la carta.
     *
     * @return array{termino?: string, tipo?: string, desde?: string, hasta?: string, sin_datos?: mixed}
     */
    public function filtros(): array
    {
        return $this->only(['termino', 'tipo', 'desde', 'hasta', 'sin_datos']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'formato' => 'formato',
            'termino' => 'término de búsqueda',
            'tipo' => 'tipo de baja',
            'desde' => 'fecha desde',
            'hasta' => 'fecha hasta',
            'sin_datos' => 'solo sin datos',
        ];
    }
}
