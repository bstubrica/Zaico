<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Equipos" subtitle="Inventario de activos de TI" icon="cube">
            <x-slot name="actions">
                <a href="{{ route('reportes.activos', request()->query()) }}" class="btn btn-secondary">
                    <x-icon name="download" :size="18" /> Exportar CSV
                </a>
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('activos.create') }}" class="btn btn-primary">
                        <x-icon name="plus" :size="18" /> Registrar equipo
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('activos.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre, serial, etiqueta…" class="form-control">
            </div>
            <div>
                <label for="categoria" class="form-label">Categoría</label>
                <select id="categoria" name="categoria" class="form-control">
                    <option value="">Todas</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria }}" @selected(($filtros['categoria'] ?? '') === $categoria)>
                            {{ $categoria }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($grupos as $grupo)
                        <option value="{{ $grupo }}" @selected(($filtros['estado'] ?? '') === $grupo)>
                            {{ $grupo }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="asignado_a" class="form-label">Asignado a</label>
                <input type="text" id="asignado_a" name="asignado_a" value="{{ $filtros['asignado_a'] ?? '' }}"
                       placeholder="Nombre de la persona" class="form-control">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-neutral flex-1">
                    <x-icon name="funnel" :size="18" /> Filtrar
                </button>
                <a href="{{ route('activos.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    {{-- Listado --}}
    <div class="card"
         x-data="{
             vista: localStorage.getItem('activos_vista') || 'tabla',
             setVista(valor) { this.vista = valor; localStorage.setItem('activos_vista', valor); },
         }">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $activos->total() }})</span>
            </h3>

            @unless ($activos->isEmpty())
                <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5" role="group"
                     aria-label="Forma de visualización">
                    <button type="button" @click="setVista('tabla')" :aria-pressed="vista === 'tabla'"
                            :class="vista === 'tabla' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition">
                        <x-icon name="list" :size="16" /> Tabla
                    </button>
                    <button type="button" @click="setVista('tarjetas')" :aria-pressed="vista === 'tarjetas'"
                            :class="vista === 'tarjetas' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition">
                        <x-icon name="grid" :size="16" /> Tarjetas
                    </button>
                </div>
            @endunless
        </div>

        @if ($activos->isEmpty())
            <x-empty-state title="No hay equipos que coincidan"
                           description="Ajusta los filtros o registra un nuevo equipo en el inventario." icon="cube">
                <x-slot name="action">
                    @if (auth()->user()->puedeEscribir())
                        <a href="{{ route('activos.create') }}" class="btn btn-primary">
                            <x-icon name="plus" :size="18" /> Registrar equipo
                        </a>
                    @endif
                </x-slot>
            </x-empty-state>
        @else
            {{-- Vista de tabla --}}
            <div x-show="vista === 'tabla'" x-cloak class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Etiqueta</th>
                            <th>Nombre</th>
                            <th class="hidden md:table-cell">Serial</th>
                            <th class="hidden md:table-cell">Categoría</th>
                            <th class="hidden lg:table-cell">Ubicación</th>
                            <th class="hidden lg:table-cell">Asignado a</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activos as $activo)
                            <tr>
                                <td class="font-medium">
                                    <a class="link" href="{{ route('activos.show', $activo) }}">
                                        {{ $activo->Etiqueta_activo }}
                                    </a>
                                </td>
                                <td>{{ $activo->Nombre_de_activo }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $activo->Serial ?? '—' }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $activo->Categoria ?? '—' }}</td>
                                <td class="hidden text-slate-500 lg:table-cell">{{ $activo->Ubicacion ?? '—' }}</td>
                                <td class="hidden text-slate-500 lg:table-cell">{{ $activo->AsignadoA ?? '—' }}</td>
                                <td><x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" /></td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('activos.show', $activo) }}" class="link">Ver</a>
                                    @if (auth()->user()->puedeEscribir())
                                        <a href="{{ route('activos.edit', $activo) }}" class="link ms-3">Editar</a>
                                        <x-delete-form :action="route('activos.destroy', $activo)"
                                                       title="Eliminar equipo"
                                                       :message="'¿Eliminar «' . $activo->Nombre_de_activo . '» (' . $activo->Etiqueta_activo . ')? Se borrarán también su compra, mantenimientos y auditoría. Esta acción no se puede deshacer.'"
                                                       class="ms-2"
                                                       aria-label="Eliminar equipo">
                                            <x-icon name="trash" :size="16" />
                                        </x-delete-form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Vista de tarjetas --}}
            <div x-show="vista === 'tarjetas'" x-cloak class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($activos as $activo)
                        <x-activo-card :activo="$activo" />
                    @endforeach
                </div>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $activos->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
