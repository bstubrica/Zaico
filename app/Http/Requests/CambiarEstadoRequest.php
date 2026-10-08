<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoRequest extends FormRequest
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
            'fk_estado' => ['required', 'exists:ESTADOS_ACTIVO,id'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fk_estado.required' => 'Debe seleccionar el estado nuevo',
            'fk_estado.exists' => 'El estado seleccionado no existe en el catálogo',
            'motivo.required' => 'El motivo del cambio es obligatorio',
            'motivo.max' => 'El motivo no puede exceder 500 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fk_estado' => 'estado nuevo',
            'motivo' => 'motivo',
        ];
    }
}
