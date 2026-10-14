<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUbicacionRequest extends FormRequest
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
        $ubicacionId = $this->route('ubicacion')?->id;

        return [
            'Nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('UBICACIONES', 'Nombre')->ignore($ubicacionId),
            ],
            'Descripcion' => ['nullable', 'string', 'max:1000'],
            'Estado' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nombre.required' => 'El nombre de la ubicación es obligatorio',
            'Nombre.unique' => 'Ya existe una ubicación con ese nombre',
            'Nombre.max' => 'El nombre no puede exceder 150 caracteres',
            'Descripcion.max' => 'La descripción no puede exceder 1000 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nombre' => 'nombre',
            'Descripcion' => 'descripción',
        ];
    }
}
