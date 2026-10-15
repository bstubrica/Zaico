<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DesincorporacionRules;
use App\Services\ActivoService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesincorporarActivosMasivoRequest extends FormRequest
{
    use DesincorporacionRules;

    public function authorize(): bool
    {
        return $this->user()?->puedeEscribir() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'todos' => ['sometimes', 'boolean'],
            'ids' => [
                Rule::requiredIf(! $this->boolean('todos')),
                'array',
                'min:1',
                'max:'.ActivoService::MAXIMO_LOTE,
            ],
            'ids.*' => ['integer', 'exists:ACTIVOS,id'],
            'termino' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'asignado_a' => ['nullable', 'string', 'max:255'],
        ], $this->reglasDesincorporacion());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'ids.required' => 'Debe seleccionar al menos un equipo',
            'ids.min' => 'Debe seleccionar al menos un equipo',
            'ids.max' => 'No se pueden procesar más de '.ActivoService::MAXIMO_LOTE.' equipos a la vez; acote el filtro.',
        ], $this->mensajesDesincorporacion());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge([
            'ids' => 'equipos',
        ], $this->atributosDesincorporacion());
    }
}
