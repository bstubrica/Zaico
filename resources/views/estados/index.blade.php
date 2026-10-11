<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Estados de catálogo" subtitle="Catálogo de estados de los activos" icon="tag">
            @if (auth()->user()->isAdmin())
                <x-slot name="actions">
                    <a href="{{ route('estados.create') }}" class="btn btn-primary">
                        <x-icon name="plus" :size="18" /> Nuevo estado
                    </a>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="card">
            @if ($estados->isEmpty())
                <x-empty-state title="El catálogo de estados está vacío"
                               description="Ejecute php artisan db:seed --class=EstadoSeeder para cargarlo." icon="tag" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Grupo</th>
                                <th class="text-center">Deployed</th>
                                <th class="text-center">Deployable</th>
                                <th class="text-center">Activos</th>
                                @if (auth()->user()->isAdmin())
                                    <th class="text-right">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($estados as $estado)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $estado->Nombre }}</td>
                                    <td><x-status-badge :grupo="$estado->Grupo" /></td>
                                    <td class="text-center">
                                        @if ($estado->Deployed)
                                            <x-icon name="check-circle" :size="18" class="mx-auto text-emerald-500" />
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($estado->Deployable)
                                            <x-icon name="check-circle" :size="18" class="mx-auto text-emerald-500" />
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-slate-500">{{ $estado->activos_count ?? $estado->activos()->count() }}</td>
                                    @if (auth()->user()->isAdmin())
                                        <td>
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('estados.edit', $estado) }}" class="btn btn-ghost btn-sm" title="Editar">
                                                    <x-icon name="pencil" :size="16" />
                                                </a>
                                                <x-delete-form :action="route('estados.destroy', $estado)"
                                                               title="Eliminar estado del catálogo"
                                                               message="¿Eliminar «{{ $estado->Nombre }}» del catálogo? Esta acción no se puede deshacer."
                                                               aria-label="Eliminar estado">
                                                    <x-icon name="trash" :size="16" />
                                                </x-delete-form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>