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
        $grupo = $this->input('Estado', $this->route('activo')?->Estado);

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
            'fk_ubicacion' => ['nullable', Rule::exists('UBICACIONES', 'id')],
            'Fabricante' => ['nullable', 'string', 'max:100'],
            'Categoria' => ['nullable', 'string', 'max:100'],
            'Modelo' => ['nullable', 'string', 'max:100'],
            'fk_modelo' => ['nullable', Rule::exists('MODELOS_ACTIVO', 'id')],
            'Observaciones' => ['nullable', 'string'],
            'Direccion_MAC' => ['nullable', 'string', 'mac_address', 'max:17'],
            'Estado' => ['sometimes', 'required', 'string', 'max:30', Rule::exists('ESTADOS_ACTIVO', 'Grupo')->where('Estado', 1)],
            'fk_estado' => [
                'nullable',
                Rule::exists('ESTADOS_ACTIVO', 'id')->where('Grupo', $grupo),
            ],
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
            'Direccion_MAC.mac_address' => 'La dirección MAC no es válida',
            'fk_ubicacion.exists' => 'La ubicación seleccionada no es válida',
            'fk_modelo.exists' => 'El modelo seleccionado no es válido',
            'Estado.required' => 'El estado es obligatorio',
            'Estado.exists' => 'El grupo de estado seleccionado no es válido',
            'fk_estado.exists' => 'El detalle de estado no corresponde al grupo seleccionado',
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
            'fk_ubicacion' => 'ubicación',
            'fk_modelo' => 'modelo',
            'Direccion_MAC' => 'dirección MAC',
            'fk_estado' => 'estado de catálogo',
        ];
    }
}
