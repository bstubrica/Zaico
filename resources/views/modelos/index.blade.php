<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Modelos" subtitle="Catálogo de modelos de activos" icon="cpu-chip">
            <x-slot name="actions">
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('modelos.create') }}" class="btn btn-primary">
                        <x-icon name="plus" :size="18" /> Registrar modelo
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('modelos.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre, fabricante o categoría" class="form-control">
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
                <a href="{{ route('modelos.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $modelos->total() }})</span>
            </h3>
        </div>

        @if ($modelos->isEmpty())
            <x-empty-state title="No hay modelos registrados"
                           description="Registra modelos para clasificar los activos por fabricante y categoría." icon="cpu-chip">
                <x-slot name="action">
                    @if (auth()->user()->puedeEscribir())
                        <a href="{{ route('modelos.create') }}" class="btn btn-primary">
                            <x-icon name="plus" :size="18" /> Registrar modelo
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
                            <th class="hidden md:table-cell">Fabricante</th>
                            <th class="hidden md:table-cell">Categoría</th>
                            <th class="text-center">Activos</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modelos as $modelo)
                            <tr>
                                <td class="font-medium">{{ $modelo->Nombre }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $modelo->Fabricante ?? '—' }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $modelo->Categoria ?? '—' }}</td>
                                <td class="text-center">
                                    @if ($modelo->activos_count > 0)
                                        <x-badge color="sky">{{ $modelo->activos_count }}</x-badge>
                                    @else
                                        <x-badge color="slate">0</x-badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($modelo->Estado === 'Activo')
                                        <x-badge color="emerald" :dot="true">Activo</x-badge>
                                    @else
                                        <x-badge color="slate" :dot="true">{{ $modelo->Estado }}</x-badge>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if (auth()->user()->puedeEscribir())
                                        <a href="{{ route('modelos.edit', $modelo) }}" class="link">Editar</a>
                                        <form method="POST" action="{{ route('modelos.destroy', $modelo) }}" class="inline"
                                              onsubmit="return confirm('¿Dar de baja el modelo {{ $modelo->Nombre }}?')">
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
                {{ $modelos->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
