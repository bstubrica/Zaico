<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    /**
     * Crea un usuario. El rol se asigna con forceFill porque no es un campo
     * masivamente asignable.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $datos
     */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
            ]);

            $usuario->forceFill(['role' => $datos['role']])->save();

            return $usuario;
        });
    }

    /**
     * Actualiza los datos del usuario y, opcionalmente, su contraseña.
     *
     * @param  array{name: string, email: string, role: string, password?: string|null}  $datos
     */
    public function actualizar(User $usuario, array $datos): User
    {
        $usuario->forceFill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'role' => $datos['role'],
        ]);

        if (! empty($datos['password'])) {
            $usuario->password = $datos['password'];
        }

        $usuario->save();

        return $usuario;
    }

    /**
     * No elimina usuarios con registros asociados (asignaciones,
     * mantenimientos, auditoría o importaciones firmados por ellos).
     */
    public function eliminar(User $usuario): void
    {
        $usuario->delete();
    }
}
