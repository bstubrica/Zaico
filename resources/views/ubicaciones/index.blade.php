<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Ubicaciones" subtitle="Catálogo de ubicaciones físicas de los activos" icon="map-pin">
            <x-slot name="actions">
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('ubicaciones.create') }}" class="btn btn-primary">
                        <x-icon name="plus" :size="18" /> Registrar ubicación
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('ubicaciones.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre o descripción" class="form-control">
            </div>
            <div>
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="Activo" @selected(($filtros['estado'] ?? '') === 'Activo')>Activo</option>
                    <option value="Inactivo" @selected(($filtros['estado'] ?? '') === 'Inactivo')>Inactivo</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-neutral flex-1">
                    <x-icon name="funnel" :size="18" /> Filtrar
                </button>
                <a href="{{ route('ubicaciones.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $ubicaciones->total() }})</span>
            </h3>
        </div>

        @if ($ubicaciones->isEmpty())
            <x-empty-state title="No hay ubicaciones registradas"
                           description="Registra ubicaciones para clasificar dónde se encuentran los activos." icon="map-pin">
                <x-slot name="action">
                    @if (auth()->user()->puedeEscribir())
                        <a href="{{ route('ubicaciones.create') }}" class="btn btn-primary">
                            <x-icon name="plus" :size="18" /> Registrar ubicación
                        </a>
                    @endif
                </x-slot>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th class="hidden md:table-cell">Descripción</th>
                            <th class="text-center">Activos</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ubicaciones as $ubicacion)
                            <tr>
                                <td class="font-medium">{{ $ubicacion->Nombre }}</td>
                                <td class="hidden text-slate-500 md:table-cell">
                                    {{ $ubicacion->Descripcion ? \Illuminate\Support\Str::limit($ubicacion->Descripcion, 80) : '—' }}
                                </td>
                                <td class="text-center">
                                    @if ($ubicacion->activos_count > 0)
                                        <x-badge color="sky">{{ $ubicacion->activos_count }}</x-badge>
                                    @else
                                        <x-badge color="slate">0</x-badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($ubicacion->Estado === 'Activo')
                                        <x-badge color="emerald" :dot="true">Activo</x-badge>
                                    @else
                                        <x-badge color="slate" :dot="true">{{ $ubicacion->Estado }}</x-badge>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if (auth()->user()->puedeEscribir())
                                        <a href="{{ route('ubicaciones.edit', $ubicacion) }}" class="link">Editar</a>
                                        <form method="POST" action="{{ route('ubicaciones.destroy', $ubicacion) }}" class="inline"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Dar de baja la ubicación '.$ubicacion->Nombre.'?') }})">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="link ms-3 text-rose-600">Baja</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $ubicaciones->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
