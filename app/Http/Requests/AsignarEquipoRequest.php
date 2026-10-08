<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AsignarEquipoRequest extends FormRequest
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
            'fk_Personal' => ['required', 'exists:PERSONAL,id'],
            'Fecha_asignacion' => ['required', 'date'],
            'Observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fk_Personal.required' => 'Debe seleccionar la persona asignataria',
            'fk_Personal.exists' => 'La persona seleccionada no existe',
            'Fecha_asignacion.required' => 'La fecha de asignación es obligatoria',
            'Fecha_asignacion.date' => 'La fecha de asignación no es válida',
            'Observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fk_Personal' => 'personal',
            'Fecha_asignacion' => 'fecha de asignación',
            'Observaciones' => 'observaciones',
        ];
    }
}
