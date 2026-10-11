<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Usuarios" subtitle="Administración de cuentas y roles" icon="users">
            <x-slot name="actions">
                <a href="{{ route('usuarios.create') }}" class="btn btn-primary">
                    <x-icon name="plus" :size="18" /> Nuevo usuario
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="card">
            @if ($usuarios->isEmpty())
                <x-empty-state title="No hay usuarios" description="Registre el primer usuario del sistema." icon="users" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th class="hidden md:table-cell">Registrado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($usuarios as $usuario)
                                <tr>
                                    <td class="font-medium text-slate-900">
                                        {{ $usuario->name }}
                                        @if ($usuario->id === auth()->id())
                                            <span class="text-xs text-slate-400">(usted)</span>
                                        @endif
                                    </td>
                                    <td class="text-slate-600">{{ $usuario->email }}</td>
                                    <td>
                                        @if ($usuario->isAdmin())
                                            <x-badge color="brand">{{ $usuario->role }}</x-badge>
                                        @elseif ($usuario->puedeEscribir())
                                            <x-badge color="sky">{{ $usuario->role }}</x-badge>
                                        @else
                                            <x-badge color="slate">{{ $usuario->role }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="hidden text-slate-500 md:table-cell">{{ $usuario->created_at?->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-ghost btn-sm" title="Editar">
                                                <x-icon name="pencil" :size="16" />
                                            </a>
                                            @if ($usuario->id !== auth()->id())
                                                <x-delete-form :action="route('usuarios.destroy', $usuario)"
                                                               title="Eliminar usuario"
                                                               :message="'¿Eliminar a «' . $usuario->name . '»? El usuario quedará sin acceso al sistema. Esta acción no se puede deshacer.'"
                                                               aria-label="Eliminar usuario">
                                                    <x-icon name="trash" :size="16" />
                                                </x-delete-form>
                                            @endif
                                        </div>
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
    </div>
</x-app-layout>