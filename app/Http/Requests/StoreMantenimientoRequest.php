<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMantenimientoRequest extends FormRequest
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
            'Tipo' => ['required', 'string', 'in:Preventivo,Correctivo,Otro'],
            'Descripcion' => ['required', 'string', 'max:2000'],
            'Fecha_mantenimiento' => ['required', 'date'],
            'evidencias' => ['sometimes', 'array'],
            'evidencias.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Tipo.required' => 'El tipo de mantenimiento es obligatorio',
            'Tipo.in' => 'El tipo de mantenimiento no es válido',
            'Descripcion.required' => 'La descripción es obligatoria',
            'Fecha_mantenimiento.required' => 'La fecha del mantenimiento es obligatoria',
            'Fecha_mantenimiento.date' => 'La fecha del mantenimiento no es válida',
            'evidencias.*.file' => 'Cada evidencia debe ser un archivo',
            'evidencias.*.mimes' => 'Las evidencias deben ser JPG, PNG, PDF o MP4',
            'evidencias.*.max' => 'Cada evidencia no puede exceder 20 MB',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Tipo' => 'tipo',
            'Descripcion' => 'descripción',
            'Fecha_mantenimiento' => 'fecha del mantenimiento',
            'evidencias' => 'evidencias',
        ];
    }
}
