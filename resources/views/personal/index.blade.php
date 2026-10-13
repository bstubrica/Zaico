<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Personal" subtitle="Personas con equipos asignados" icon="users">
            <x-slot name="actions">
                <a href="{{ route('reportes.personal', request()->query()) }}" class="btn btn-secondary">
                    <x-icon name="download" :size="18" /> Exportar CSV
                </a>
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('personal.create') }}" class="btn btn-primary">
                        <x-icon name="plus" :size="18" /> Registrar persona
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('personal.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre, apellido, usuario, cargo" class="form-control">
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
                <a href="{{ route('personal.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card"
         x-data="seleccionLote({ clave: 'sel:personal', total: {{ $personas->total() }}, pagina: {{ json_encode($personas->pluck('id')->all()) }}, mensaje: 'Se darán de baja {n} personas. No podrán recibir equipos.', mensajeGrande: 'Son {n} personas. La baja se aplicará de una sola vez a todas las seleccionadas. ¿Continuar?' })">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $personas->total() }})</span>
            </h3>
        </div>

        @if ($personas->isEmpty())
            <x-empty-state title="No hay personas registradas"
                           description="Registra personas para poder asignarles equipos." icon="users">
                <x-slot name="action">
                    @if (auth()->user()->puedeEscribir())
                        <a href="{{ route('personal.create') }}" class="btn btn-primary">
                            <x-icon name="plus" :size="18" /> Registrar persona
                        </a>
                    @endif
                </x-slot>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            @if (auth()->user()->puedeEscribir())
                                <th class="w-10">
                                    <input type="checkbox" aria-label="Seleccionar todos"
                                           class="h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                           :checked="todosVisibles()"
                                           @change="alternarVisibles($event.target.checked)">
                                </th>
                            @endif
                            <th>Nombre completo</th>
                            <th>Usuario</th>
                            <th class="hidden md:table-cell">Cargo</th>
                            <th class="text-center">Equipos asignados</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($personas as $persona)
                            <tr>
                                @if (auth()->user()->puedeEscribir())
                                    <td class="w-10">
                                        <input type="checkbox" value="{{ $persona->id }}"
                                               class="h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                               :checked="estaSeleccionado({{ $persona->id }})"
                                               @change="alternar({{ $persona->id }}, $event.target.checked)">
                                    </td>
                                @endif
                                <td class="font-medium">
                                    <a class="link" href="{{ route('personal.show', $persona) }}">{{ $persona->NombreCompleto }}</a>
                                </td>
                                <td class="text-slate-500">{{ $persona->Nombre_usuario }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $persona->Cargo ?? '—' }}</td>
                                <td class="text-center">
                                    @if ($persona->asignaciones_activas > 0)
                                        <x-badge color="sky">{{ $persona->asignaciones_activas }}</x-badge>
                                    @else
                                        <x-badge color="slate">0</x-badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($persona->Estado === 'Activo')
                                        <x-badge color="emerald" :dot="true">Activo</x-badge>
                                    @else
                                        <x-badge color="slate" :dot="true">{{ $persona->Estado }}</x-badge>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('personal.show', $persona) }}" class="link">Ver</a>
                                    @if (auth()->user()->puedeEscribir())
                                        <a href="{{ route('personal.edit', $persona) }}" class="link ms-3">Editar</a>
                                        <form method="POST" action="{{ route('personal.destroy', $persona) }}" class="inline"
                                              onsubmit="return confirm('¿Dar de baja a {{ $persona->NombreCompleto }}?')">
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

            @if (auth()->user()->puedeEscribir())
                <x-seleccion.formulario id="bulk-personal" method="POST"
                                        action="{{ route('personal.baja-masiva') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger" :disabled="cantidad() === 0">
                        <x-icon name="archive" :size="18" /> Dar de baja
                    </button>
                    @foreach (['termino', 'estado'] as $campo)
                        @if (filled($filtros[$campo] ?? null))
                            <input type="hidden" name="{{ $campo }}" value="{{ $filtros[$campo] }}">
                        @endif
                    @endforeach
                </x-seleccion.formulario>
            @endif

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $personas->links() }}
            </div>
        @endif
    </div>

    <x-resultado-lote />
</x-app-layout>
