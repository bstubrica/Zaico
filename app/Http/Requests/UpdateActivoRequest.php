<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivoRequest extends FormRequest
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
        $activoId = $this->route('activo')?->id;

        return [
            'Nombre_de_activo' => ['sometimes', 'required', 'string', 'min:3', 'max:150'],
            'Etiqueta_activo' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('ACTIVOS', 'Etiqueta_activo')->ignore($activoId),
            ],
            'Serial' => [
                'nullable', 'string', 'max:100',
                Rule::unique('ACTIVOS', 'Serial')->ignore($activoId),
            ],
            'Ubicacion' => ['nullable', 'string', 'max:150'],
            'Ubicacion_Predeterminada' => ['nullable', 'string', 'max:150'],
            'Fabricante' => ['nullable', 'string', 'max:100'],
            'Categoria' => ['nullable', 'string', 'max:100'],
            'Modelo' => ['nullable', 'string', 'max:100'],
            'Observaciones' => ['nullable', 'string'],
            'Direccion_MAC' => ['nullable', 'string', 'mac_addr', 'max:17'],
            'Estado' => ['sometimes', 'required', 'string', 'max:20'],
            'fk_estado' => ['nullable', 'exists:ESTADOS_ACTIVO,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nombre_de_activo.required' => 'El nombre del activo es obligatorio',
            'Etiqueta_activo.required' => 'La etiqueta es obligatoria',
            'Etiqueta_activo.unique' => 'Ya existe un activo con esa etiqueta',
            'Serial.unique' => 'Ya existe un activo con ese número de serie',
            'Direccion_MAC.mac_addr' => 'La dirección MAC no es válida',
            'Estado.required' => 'El estado es obligatorio',
            'fk_estado.exists' => 'El estado seleccionado no existe en el catálogo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nombre_de_activo' => 'nombre del activo',
            'Etiqueta_activo' => 'etiqueta',
            'Serial' => 'número de serie',
            'Direccion_MAC' => 'dirección MAC',
            'fk_estado' => 'estado de catálogo',
        ];
    }
}
