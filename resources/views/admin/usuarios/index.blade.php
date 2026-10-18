<x-app-layout>
    @php
        $etiquetasRol = [
            'Admin' => 'Administrador',
            'SoporteTecnico' => 'Soporte Técnico',
            'Auditor' => 'Auditor',
        ];
        $coloresRol = [
            'Admin' => 'brand',
            'SoporteTecnico' => 'sky',
            'Auditor' => 'slate',
        ];
    @endphp

    <x-slot name="header">
        <x-page-header title="Usuarios del sistema" subtitle="Cuentas de acceso, roles y estado de habilitación" icon="users">
            <x-slot name="actions">
                <a href="{{ route('admin.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Administración
                </a>
                <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
                    <x-icon name="user-plus" :size="18" /> Nuevo usuario
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('admin.usuarios.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre o correo electrónico" class="form-control">
            </div>
            <div>
                <label for="rol" class="form-label">Rol</label>
                <select id="rol" name="rol" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol }}" @selected(($filtros['rol'] ?? '') === $rol)>
                            {{ $etiquetasRol[$rol] ?? $rol }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="habilitado" @selected(($filtros['estado'] ?? '') === 'habilitado')>Habilitado</option>
                    <option value="deshabilitado" @selected(($filtros['estado'] ?? '') === 'deshabilitado')>Deshabilitado</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <a href="{{ route('admin.usuarios.index') }}" class="btn btn-ghost">Limpiar</a>
            <button type="submit" class="btn btn-neutral">
                <x-icon name="funnel" :size="18" /> Filtrar
            </button>
        </div>
    </form>

    <div class="card mt-5">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $usuarios->total() }})</span>
            </h3>
        </div>

        @if ($usuarios->isEmpty())
            <x-empty-state title="No se encontraron usuarios"
                           description="Ajuste los filtros o registre un nuevo usuario." icon="users">
                <x-slot name="action">
                    <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
                        <x-icon name="user-plus" :size="18" /> Nuevo usuario
                    </a>
                </x-slot>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $usuario)
                            <tr>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $usuario->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $usuario->email }}</span>
                                </td>
                                <td>
                                    <x-badge :color="$coloresRol[$usuario->role] ?? 'slate'">
                                        {{ $etiquetasRol[$usuario->role] ?? $usuario->role }}
                                    </x-badge>
                                </td>
                                <td>
                                    @if ($usuario->estaActivo())
                                        <x-badge color="emerald" :dot="true">Habilitado</x-badge>
                                    @else
                                        <x-badge color="slate" :dot="true">Deshabilitado</x-badge>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="link">Editar</a>

                                    @if ($usuario->is(auth()->user()))
                                        <span class="badge ms-3 bg-brand-50 text-brand-700">Sesión actual</span>
                                    @elseif ($usuario->estaActivo())
                                        <form method="POST" action="{{ route('admin.usuarios.toggle', $usuario) }}" class="inline"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Deshabilitar a '.$usuario->name.'? No podrá iniciar sesión hasta que se habilite de nuevo.') }})">
                                            @csrf
                                            <button type="submit" class="link ms-3 text-rose-600">
                                                <x-icon name="lock-closed" :size="15" class="inline align-text-bottom" /> Deshabilitar
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.usuarios.toggle', $usuario) }}" class="inline"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Habilitar a '.$usuario->name.'?') }})">
                                            @csrf
                                            <button type="submit" class="link ms-3 text-emerald-600">
                                                <x-icon name="lock-open" :size="15" class="inline align-text-bottom" /> Habilitar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $usuarios->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
