<?php

namespace App\Http\Requests;

use App\Models\EstadoActivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstadoRequest extends FormRequest
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
        $estadoId = $this->route('estado')?->id;

        return [
            'Nombre' => [
                'required', 'string', 'max:60',
                Rule::unique('ESTADOS_ACTIVO', 'Nombre')->ignore($estadoId),
            ],
            'Grupo' => ['required', 'string', Rule::in(EstadoActivo::GRUPOS)],
            'Deployed' => ['sometimes', 'boolean'],
            'Deployable' => ['sometimes', 'boolean'],
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
            'Grupo.in' => 'El grupo seleccionado no es válido',
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
            'Deployed' => 'desplegado',
            'Deployable' => 'desplegable',
        ];
    }
}
