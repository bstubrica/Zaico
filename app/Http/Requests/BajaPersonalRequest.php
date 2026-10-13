<?php

namespace App\Http\Requests;

use App\Services\PersonalService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BajaPersonalRequest extends FormRequest
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
                'max:'.PersonalService::MAXIMO_LOTE,
            ],
            'ids.*' => ['integer', 'exists:PERSONAL,id'],
            'termino' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos una persona',
            'ids.min' => 'Debe seleccionar al menos una persona',
            'ids.max' => 'No se pueden procesar más de '.PersonalService::MAXIMO_LOTE.' personas a la vez; acote el filtro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'personas',
        ];
    }
}
