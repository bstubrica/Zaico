<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DesincorporacionRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DesincorporarActivoRequest extends FormRequest
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
        return $this->reglasDesincorporacion();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesDesincorporacion();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->atributosDesincorporacion();
    }
}
