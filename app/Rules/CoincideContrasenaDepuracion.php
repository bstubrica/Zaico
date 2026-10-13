<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Verifica la contraseña de depuración contra el hash almacenado en
 * configuración (independiente de la base de datos, que será vaciada).
 */
class CoincideContrasenaDepuracion implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hash = config('zaico.depuracion.password_hash');

        if (blank($hash)) {
            $fail('La contraseña de depuración no está configurada en el servidor.');

            return;
        }

        if (! is_string($value) || ! Hash::check($value, (string) $hash)) {
            $fail('La contraseña de depuración no es correcta.');
        }
    }
}
