<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstadoActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Nombre' => [
                'required',
                'string',
                'max:60',
                Rule::unique('ESTADOS_ACTIVO', 'Nombre')->ignore($this->route('estado')?->getKey()),
            ],
            'Grupo' => ['required', 'string', 'max:30'],
            'Deployed' => ['sometimes', 'boolean'],
            'Deployable' => ['sometimes', 'boolean'],
            'Estado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nombre.required' => 'El nombre del estado es obligatorio',
            'Nombre.unique' => 'Ya existe un estado con ese nombre',
            'Nombre.max' => 'El nombre no puede exceder 60 caracteres',
            'Grupo.required' => 'El grupo es obligatorio',
            'Grupo.max' => 'El grupo no puede exceder 30 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nombre' => 'nombre',
            'Grupo' => 'grupo',
            'Deployed' => 'deployed',
            'Deployable' => 'deployable',
            'Estado' => 'estado',
        ];
    }
}
