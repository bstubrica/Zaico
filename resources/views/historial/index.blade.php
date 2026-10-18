<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Historial de asignaciones" subtitle="Trazabilidad de entregas y devoluciones" icon="clock">
            <x-slot name="actions">
                <a href="{{ route('reportes.historial', request()->query()) }}" class="btn btn-secondary">
                    <x-icon name="download" :size="18" /> Exportar CSV
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('historial.index') }}" class="card card-body">
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
                <label for="personal_id" class="form-label">Persona</label>
                <x-search-select
                    name="personal_id"
                    placeholder="Todas"
                    :selected="$filtros['personal_id'] ?? ''"
                    :options="$personas->map(fn ($persona) => [
                        'value' => $persona->id,
                        'label' => $persona->NombreCompleto,
                        'meta' => $persona->Cargo ?? '',
                    ])->values()->all()" />
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-neutral flex-1">
                    <x-icon name="funnel" :size="18" /> Filtrar
                </button>
                <a href="{{ route('historial.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $asignaciones->total() }})</span>
            </h3>
        </div>

        @if ($asignaciones->isEmpty())
            <x-empty-state title="No hay asignaciones registradas" description="Ajusta los filtros o registra asignaciones desde la ficha de un equipo." icon="clock" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha asignación</th>
                            <th>Equipo</th>
                            <th>Persona</th>
                            <th class="hidden md:table-cell">Devuelto</th>
                            <th>Estado</th>
                            <th class="hidden lg:table-cell">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asignaciones as $asignacion)
                            <tr>
                                <td>{{ $asignacion->Fecha_asignacion?->format('d/m/Y') }}</td>
                                <td class="font-medium">
                                    <a class="link" href="{{ route('activos.show', $asignacion->fk_Activo) }}">
                                        {{ $asignacion->activo?->Etiqueta_activo }}
                                    </a>
                                    <span class="block text-xs text-slate-500">{{ $asignacion->activo?->Nombre_de_activo }}</span>
                                </td>
                                <td>{{ $asignacion->personal?->NombreCompleto ?? '—' }}</td>
                                <td class="hidden md:table-cell">{{ $asignacion->Fecha_devolucion?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if ($asignacion->Estado === 'Activa')
                                        <x-badge color="sky" :dot="true">Activa</x-badge>
                                    @else
                                        <x-badge color="slate" :dot="true">{{ $asignacion->Estado }}</x-badge>
                                    @endif
                                </td>
                                <td class="hidden text-slate-500 lg:table-cell">{{ filled($asignacion->Observaciones) ? \Illuminate\Support\Str::limit($asignacion->Observaciones, 60) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $asignaciones->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
