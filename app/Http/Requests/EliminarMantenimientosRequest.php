<?php

namespace App\Http\Requests;

use App\Services\MantenimientoService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EliminarMantenimientosRequest extends FormRequest
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
            'todos' => ['sometimes', 'boolean'],
            'ids' => [
                Rule::requiredIf(! $this->boolean('todos')),
                'array',
                'min:1',
                'max:'.MantenimientoService::MAXIMO_LOTE,
            ],
            'ids.*' => ['integer', 'exists:MANTENIMIENTOS,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'activo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos un mantenimiento',
            'ids.min' => 'Debe seleccionar al menos un mantenimiento',
            'ids.max' => 'No se pueden procesar más de '.MantenimientoService::MAXIMO_LOTE.' mantenimientos a la vez; acote el filtro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'mantenimientos',
        ];
    }
}
