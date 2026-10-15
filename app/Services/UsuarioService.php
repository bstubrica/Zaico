<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Gestión de usuarios del sistema (alta, modificación y habilitación).
 *
 * `role` y `activo` no son asignables en masa, por lo que se fijan de forma
 * explícita. Se garantiza que el sistema nunca quede sin un administrador
 * activo.
 */
class UsuarioService
{
    public const MENSAJE_ULTIMO_ADMIN = 'Debe existir al menos un administrador activo.';

    /**
     * Listado de usuarios del sistema con filtros básicos.
     *
     * @param  array{termino?: string|null, rol?: string|null, estado?: string|null}  $filtros
     */
    public function listar(array $filtros, int $tamano = 15): LengthAwarePaginator
    {
        return User::query()
            ->when($filtros['termino'] ?? null, fn ($query, $termino) => $query->where(
                fn ($where) => $where
                    ->where('name', 'like', "%{$termino}%")
                    ->orWhere('email', 'like', "%{$termino}%"),
            ))
            ->when($filtros['rol'] ?? null, fn ($query, $rol) => $query->where('role', $rol))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('activo', $estado === 'habilitado'))
            ->orderBy('name')
            ->paginate($tamano)
            ->withQueryString();
    }

    /**
     * @param  array{name: string, email: string, role: string, password: string, activo?: bool}  $datos
     */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos): User {
            $usuario = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
            ]);

            $usuario->forceFill([
                'role' => $datos['role'],
                'activo' => (bool) ($datos['activo'] ?? true),
            ])->save();

            return $usuario;
        });
    }

    /**
     * @param  array{name: string, email: string, role: string, password?: string|null, activo?: bool}  $datos
     */
    public function actualizar(User $usuario, array $datos): User
    {
        $nuevoRol = $datos['role'];
        $nuevoActivo = array_key_exists('activo', $datos)
            ? (bool) $datos['activo']
            : $usuario->estaActivo();

        $this->asegurarAdministradorActivo($usuario, $nuevoRol, $nuevoActivo);

        return DB::transaction(function () use ($usuario, $datos, $nuevoRol, $nuevoActivo): User {
            $usuario->name = $datos['name'];
            $usuario->email = $datos['email'];
            $usuario->role = $nuevoRol;
            $usuario->activo = $nuevoActivo;

            if (filled($datos['password'] ?? null)) {
                $usuario->password = Hash::make($datos['password']);
            }

            $usuario->save();

            return $usuario;
        });
    }

    public function cambiarActivo(User $usuario, bool $activo): User
    {
        $this->asegurarAdministradorActivo($usuario, $usuario->role, $activo);

        $usuario->activo = $activo;
        $usuario->save();

        return $usuario;
    }

    /**
     * Impide dejar el sistema sin ningún administrador activo.
     *
     * @throws RuntimeException
     */
    private function asegurarAdministradorActivo(User $usuario, string $nuevoRol, bool $nuevoActivo): void
    {
        $eraAdminActivo = $usuario->isAdmin() && $usuario->estaActivo();
        $seraAdminActivo = $nuevoRol === 'Admin' && $nuevoActivo;

        if (! $eraAdminActivo || $seraAdminActivo) {
            return;
        }

        $existeOtro = User::query()
            ->where('role', 'Admin')
            ->where('activo', true)
            ->whereKeyNot($usuario->getKey())
            ->exists();

        if (! $existeOtro) {
            throw new RuntimeException(self::MENSAJE_ULTIMO_ADMIN);
        }
    }
}
