<?php

namespace App\Http\Requests;

use App\Rules\CoincideContrasenaDepuracion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepurarInventarioRequest extends FormRequest
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
            'password' => ['required', 'string', new CoincideContrasenaDepuracion],
            'frase' => ['required', 'string', Rule::in([(string) config('zaico.depuracion.frase')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Debe ingresar la contraseña de depuración.',
            'frase.required' => 'Debe escribir la frase de confirmación.',
            'frase.in' => 'La frase de confirmación no coincide con la solicitada.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'contraseña de depuración',
            'frase' => 'frase de confirmación',
        ];
    }
}
