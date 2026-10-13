<?php

namespace App\Http\Requests;

use App\Services\ImportacionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EliminarImportacionesRequest extends FormRequest
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
                'max:'.ImportacionService::MAXIMO_LOTE,
            ],
            'ids.*' => ['integer', 'exists:IMPORTACIONES_LOG,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Debe seleccionar al menos una importación',
            'ids.min' => 'Debe seleccionar al menos una importación',
            'ids.max' => 'No se pueden procesar más de '.ImportacionService::MAXIMO_LOTE.' importaciones a la vez; acote el filtro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'importaciones',
        ];
    }
}
