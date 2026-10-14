<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModeloActivoRequest extends FormRequest
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
        $modeloId = $this->route('modelo')?->id;

        return [
            'Nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('MODELOS_ACTIVO', 'Nombre')->ignore($modeloId),
            ],
            'Fabricante' => ['nullable', 'string', 'max:100'],
            'Categoria' => ['nullable', 'string', 'max:100'],
            'Estado' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nombre.required' => 'El nombre del modelo es obligatorio',
            'Nombre.unique' => 'Ya existe un modelo con ese nombre',
            'Nombre.max' => 'El nombre no puede exceder 100 caracteres',
            'Fabricante.max' => 'El fabricante no puede exceder 100 caracteres',
            'Categoria.max' => 'La categoría no puede exceder 100 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nombre' => 'nombre',
            'Fabricante' => 'fabricante',
            'Categoria' => 'categoría',
        ];
    }
}
