<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminUsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    /**
     * Listado de usuarios del sistema con filtros básicos.
     */
    public function index(Request $request): View
    {
        $filtros = [
            'termino' => $request->string('termino')->trim()->value() ?: null,
            'rol' => $request->string('rol')->value() ?: null,
            'estado' => $request->string('estado')->value() ?: null,
        ];

        $usuarios = $this->usuarios->listar($filtros);

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'filtros' => $filtros,
            'roles' => User::ROLES,
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.create', ['roles' => User::ROLES]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $usuario = $this->usuarios->crear($request->validated());

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->name} creado correctamente.");
    }

    public function edit(User $usuario): View
    {
        return view('admin.usuarios.edit', [
            'usuario' => $usuario,
            'roles' => User::ROLES,
        ]);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        // Un administrador no puede quitarse a sí mismo el rol de administrador.
        if ($usuario->is($request->user()) && $datos['role'] !== 'Admin') {
            return back()->withInput()->withErrors([
                'role' => 'No puede cambiar su propio rol de administrador.',
            ]);
        }

        try {
            $this->usuarios->actualizar($usuario, $datos);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->name} actualizado correctamente.");
    }

    /**
     * Habilita o deshabilita la cuenta del usuario indicado.
     */
    public function toggleActivo(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->withErrors([
                'usuario' => 'No puede deshabilitar su propia cuenta.',
            ]);
        }

        $activar = ! $usuario->estaActivo();

        try {
            $this->usuarios->cambiarActivo($usuario, $activar);
        } catch (RuntimeException $e) {
            return back()->withErrors(['usuario' => $e->getMessage()]);
        }

        return back()->with('success', $activar
            ? "Usuario {$usuario->name} habilitado."
            : "Usuario {$usuario->name} deshabilitado.");
    }
}
