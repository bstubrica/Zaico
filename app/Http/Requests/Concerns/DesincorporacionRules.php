<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TipoDesincorporacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Reglas, mensajes y atributos compartidos por la desincorporación de un
 * equipo, la desincorporación masiva y la edición posterior del registro de
 * baja. Centralizarlas evita que las tres rutas diverjan.
 */
trait DesincorporacionRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasDesincorporacion(): array
    {
        return [
            'tipo' => ['required', Rule::in(TipoDesincorporacion::valores())],
            'destinatario' => ['required', 'string', 'max:150'],
            'detalle_destino' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'monto' => [
                'nullable', 'numeric', 'min:0',
                Rule::requiredIf(fn (): bool => $this->input('tipo') === TipoDesincorporacion::Venta->value),
            ],
            'moneda' => ['nullable', 'string', 'max:3'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDesincorporacion(): array
    {
        return [
            'tipo.required' => 'El tipo de desincorporación es obligatorio',
            'tipo.in' => 'El tipo de desincorporación no es válido',
            'destinatario.required' => 'Indique a quién o dónde se desincorpora el equipo',
            'destinatario.max' => 'El destinatario no puede exceder 150 caracteres',
            'detalle_destino.max' => 'El detalle del destino no puede exceder 255 caracteres',
            'referencia.max' => 'La referencia no puede exceder 100 caracteres',
            'monto.required' => 'Una venta requiere registrar el monto',
            'monto.numeric' => 'El monto debe ser un número',
            'monto.min' => 'El monto no puede ser negativo',
            'moneda.max' => 'La moneda no puede exceder 3 caracteres',
            'fecha.required' => 'La fecha de desincorporación es obligatoria',
            'fecha.date' => 'La fecha de desincorporación no es válida',
            'motivo.required' => 'El motivo de la desincorporación es obligatorio',
            'motivo.max' => 'El motivo no puede exceder 500 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosDesincorporacion(): array
    {
        return [
            'tipo' => 'tipo',
            'destinatario' => 'destinatario',
            'detalle_destino' => 'detalle del destino',
            'referencia' => 'referencia',
            'monto' => 'monto',
            'moneda' => 'moneda',
            'fecha' => 'fecha de desincorporación',
            'motivo' => 'motivo',
        ];
    }
}
