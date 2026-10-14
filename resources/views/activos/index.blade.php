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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
            <div>
                <label for="ubicacion" class="form-label">Ubicación</label>
                <select id="ubicacion" name="ubicacion" class="form-control">
                    <option value="">Todas</option>
                    @foreach ($ubicaciones as $ubicacion)
                        <option value="{{ $ubicacion->Nombre }}" @selected(($filtros['ubicacion'] ?? '') === $ubicacion->Nombre)>
                            {{ $ubicacion->Nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="modelo" class="form-label">Modelo</label>
                <select id="modelo" name="modelo" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($modelos as $modelo)
                        <option value="{{ $modelo->Nombre }}" @selected(($filtros['modelo'] ?? '') === $modelo->Nombre)>
                            {{ $modelo->Nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 lg:col-span-2">
                <button type="submit" class="btn btn-neutral flex-1">
                    <x-icon name="funnel" :size="18" /> Filtrar
                </button>
                <a href="{{ route('activos.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
    </form>

    {{-- Listado --}}
    <div class="card"
         x-data="seleccionLote({ clave: 'sel:activos', total: {{ $activos->total() }}, pagina: {{ json_encode($activos->pluck('id')->all()) }}, mensaje: 'Se desincorporarán {n} equipos. El estado cambiará a Desincorporado y quedará registrado en el historial.', mensajeGrande: 'Son {n} equipos. La desincorporación se aplicará de una sola vez a todos los seleccionados. ¿Continuar?' })">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $activos->total() }})</span>
            </h3>
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
                                @if (auth()->user()->puedeEscribir())
                                    <td class="w-10">
                                        <input type="checkbox" value="{{ $activo->id }}"
                                               class="h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                               :checked="estaSeleccionado({{ $activo->id }})"
                                               @change="alternar({{ $activo->id }}, $event.target.checked)">
                                    </td>
                                @endif
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
                                        @if ($activo->estadoCatalogo?->Grupo !== 'Desincorporado')
                                            <button type="button" class="link ms-3 text-rose-600"
                                                    @click="$dispatch('open-modal', 'desincorporar-activo'); $dispatch('set-desincorporar', {{ $activo->id }})">
                                                Desincorporar
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (auth()->user()->puedeEscribir())
                <x-seleccion.formulario id="bulk-activos" method="POST"
                                        action="{{ route('activos.desincorporar-masivo') }}">
                    @csrf
                    <div class="min-w-[16rem] flex-1">
                        <label for="motivo-masivo" class="form-label">
                            Motivo de la desincorporación *
                        </label>
                        <input type="text" id="motivo-masivo" name="motivo" required maxlength="500"
                               class="form-control" placeholder="Ej.: Obsolescencia, reemplazo por lote…">
                    </div>
                    <button type="submit" class="btn btn-danger" :disabled="cantidad() === 0">
                        <x-icon name="trash" :size="18" /> Desincorporar seleccionados
                    </button>
                    @foreach (['termino', 'categoria', 'estado', 'ubicacion', 'modelo', 'asignado_a'] as $campo)
                        @if (filled($filtros[$campo] ?? null))
                            <input type="hidden" name="{{ $campo }}" value="{{ $filtros[$campo] }}">
                        @endif
                    @endforeach
                </x-seleccion.formulario>
            @endif

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $activos->links() }}
            </div>
        @endif
    </div>

    <x-resultado-lote />

    @if (auth()->user()->puedeEscribir())
        <x-modal name="desincorporar-activo" maxWidth="md">
            <div x-data="{ id: null }" @set-desincorporar.window="id = $event.detail">
                <form method="POST" :action="'{{ url('activos') }}/' + (id ?? '') + '/desincorporar'" class="p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-slate-900">Desincorporar equipo</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        El equipo pasará a estado <strong>Desincorporado</strong>. La operación no borra el registro ni su historial.
                    </p>

                    <x-input-label for="motivo-individual" value="Motivo *" class="mt-4" />
                    <textarea id="motivo-individual" name="motivo" rows="3" required maxlength="500"
                              class="form-control mt-1" placeholder="Ej.: Obsolescencia, daño irreparable…"></textarea>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost"
                                @click="$dispatch('close-modal', 'desincorporar-activo')">Cancelar</button>
                        <button type="submit" class="btn btn-danger" :disabled="id === null">
                            <x-icon name="trash" :size="18" /> Desincorporar
                        </button>
                    </div>
                </form>
            </div>
        </x-modal>
    @endif
</x-app-layout>
