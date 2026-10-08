<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DevolverEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puedeEscribir() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Fecha_devolucion' => ['required', 'date'],
            'Observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Fecha_devolucion.required' => 'La fecha de devolución es obligatoria',
            'Fecha_devolucion.date' => 'La fecha de devolución no es válida',
            'Observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Fecha_devolucion' => 'fecha de devolución',
            'Observaciones' => 'observaciones',
        ];
    }
}
