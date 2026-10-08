<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonalRequest extends FormRequest
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
        $personalId = $this->route('personal')?->id;

        return [
            'Nombre' => ['required', 'string', 'max:100'],
            'Apellido' => ['required', 'string', 'max:100'],
            'Nombre_usuario' => [
                'required', 'string', 'max:50',
                Rule::unique('PERSONAL', 'Nombre_usuario')->ignore($personalId),
            ],
            'Cargo' => ['nullable', 'string', 'max:100'],
            'Observaciones' => ['nullable', 'string'],
            'Estado' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nombre.required' => 'El nombre es obligatorio',
            'Nombre.max' => 'El nombre no puede exceder 100 caracteres',
            'Apellido.required' => 'El apellido es obligatorio',
            'Apellido.max' => 'El apellido no puede exceder 100 caracteres',
            'Nombre_usuario.required' => 'El nombre de usuario es obligatorio',
            'Nombre_usuario.unique' => 'Ya existe personal con ese nombre de usuario',
            'Nombre_usuario.max' => 'El nombre de usuario no puede exceder 50 caracteres',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nombre' => 'nombre',
            'Apellido' => 'apellido',
            'Nombre_usuario' => 'nombre de usuario',
            'Cargo' => 'cargo',
        ];
    }
}
