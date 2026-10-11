<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Mantenimientos" subtitle="Registro de mantenimientos de equipos" icon="wrench">
            <x-slot name="actions">
                <a href="{{ route('reportes.mantenimientos', request()->query()) }}" class="btn btn-secondary">
                    <x-icon name="download" :size="18" /> Exportar CSV
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('mantenimientos.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control">
            </div>
            <div>
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control">
            </div>
            <div>
                <label for="activo" class="form-label">Equipo</label>
                <input type="text" id="activo" name="activo" value="{{ $filtros['activo'] ?? '' }}"
                       placeholder="Nombre o etiqueta" class="form-control">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-neutral flex-1">
                    <x-icon name="funnel" :size="18" /> Filtrar
                </button>
                <a href="{{ route('mantenimientos.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $mantenimientos->total() }})</span>
            </h3>
        </div>

        @if ($mantenimientos->isEmpty())
            <x-empty-state title="No hay mantenimientos registrados"
                           description="Los mantenimientos se registran desde la ficha de cada equipo." icon="wrench" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Equipo</th>
                            <th>Tipo</th>
                            <th class="hidden md:table-cell">Descripción</th>
                            <th class="hidden md:table-cell">Registrado por</th>
                            <th class="text-center">Evidencias</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mantenimientos as $mantenimiento)
                            <tr>
                                <td>{{ $mantenimiento->Fecha_mantenimiento?->format('d/m/Y') }}</td>
                                <td class="font-medium">
                                    <a class="link" href="{{ route('activos.show', $mantenimiento->fk_activo) }}">
                                        {{ $mantenimiento->activo?->Etiqueta_activo }}
                                    </a>
                                    <span class="block text-xs text-slate-500">{{ $mantenimiento->activo?->Nombre_de_activo }}</span>
                                </td>
                                <td>
                                    <x-badge :color="$mantenimiento->Tipo === 'Preventivo' ? 'sky' : 'amber'">{{ $mantenimiento->Tipo }}</x-badge>
                                </td>
                                <td class="hidden text-slate-500 md:table-cell">{{ \Illuminate\Support\Str::limit($mantenimiento->Descripcion, 70) }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $mantenimiento->usuario?->name ?? '—' }}</td>
                                <td class="text-center">
                                    @if ($mantenimiento->evidencias->count() > 0)
                                        <x-badge color="brand">{{ $mantenimiento->evidencias->count() }}</x-badge>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $mantenimientos->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
