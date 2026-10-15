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
                <x-search-select
                    name="ubicacion"
                    placeholder="Todas"
                    :selected="$filtros['ubicacion'] ?? ''"
                    :options="$ubicaciones->map(fn ($ubicacion) => [
                        'value' => $ubicacion->Nombre,
                        'label' => $ubicacion->Nombre,
                    ])->values()->all()" />
            </div>
            <div>
                <label for="modelo" class="form-label">Modelo</label>
                <x-search-select
                    name="modelo"
                    placeholder="Todos"
                    :selected="$filtros['modelo'] ?? ''"
                    :options="$modelos->map(fn ($modelo) => [
                        'value' => $modelo->Nombre,
                        'label' => $modelo->Nombre,
                        'meta' => $modelo->Fabricante ?? '',
                    ])->values()->all()" />
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
    <div class="card" x-data="vistaListado({ clave: 'vista:activos' })">
        <div x-data="seleccionLote({ clave: 'sel:activos', total: {{ $activos->total() }}, pagina: {{ json_encode($activos->pluck('id')->all()) }}, mensaje: 'Se desincorporarán {n} equipos. El estado cambiará a Desincorporado y quedará registrado en el historial.', mensajeGrande: 'Son {n} equipos. La desincorporación se aplicará de una sola vez a todos los seleccionados. ¿Continuar?' })">
            <div class="card-header">
                <h3 class="card-title">
                    Resultados
                    <span class="ms-1 font-normal text-slate-400">({{ $activos->total() }})</span>
                </h3>
                @unless ($activos->isEmpty())
                    <x-vista-toggle />
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
            <div class="table-wrap" x-show="vista === 'tabla'" x-cloak>
                <table class="table">
                    <thead>
                        <tr>
                            @if (auth()->user()->puedeEscribir())
                                <th class="w-10">
                                    <x-sel-todos />
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
                                        <x-sel-fila :valor="$activo->id" :etiqueta="$activo->Etiqueta_activo" />
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

            {{-- Vista de tarjetas --}}
            <div x-show="vista === 'tarjeta'" x-cloak class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($activos as $activo)
                    <div class="group relative flex flex-col rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow-md">
                        @if (auth()->user()->puedeEscribir())
                            <label class="absolute right-3 top-3 z-10 inline-flex cursor-pointer items-center justify-center rounded-md bg-white/90 p-1 shadow-sm ring-1 ring-slate-200">
                                <input type="checkbox" value="{{ $activo->id }}"
                                       class="h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                       :checked="estaSeleccionado({{ $activo->id }})"
                                       @change="alternar({{ $activo->id }}, $event.target.checked)"
                                       aria-label="Seleccionar {{ $activo->Etiqueta_activo }}">
                            </label>
                        @endif

                        <a href="{{ route('activos.show', $activo) }}" class="flex gap-3 p-4">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                                @if ($activo->Imagen_Activo)
                                    <img src="{{ route('activos.imagen', $activo) }}" alt="{{ $activo->Nombre_de_activo }}"
                                         loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <x-icon name="cube" :size="26" />
                                @endif
                            </span>
                            <span class="min-w-0 flex-1 pe-6">
                                <span class="block truncate text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $activo->Etiqueta_activo }}</span>
                                <span class="mt-0.5 block truncate font-semibold text-slate-900">{{ $activo->Nombre_de_activo }}</span>
                                <span class="mt-1.5 block"><x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" /></span>
                            </span>
                        </a>

                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 border-t border-slate-100 px-4 py-3 text-xs">
                            <div class="min-w-0">
                                <dt class="text-slate-400">Categoría</dt>
                                <dd class="truncate font-medium text-slate-700">{{ $activo->Categoria ?? '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-slate-400">Serial</dt>
                                <dd class="truncate font-medium text-slate-700">{{ $activo->Serial ?? '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-slate-400">Ubicación</dt>
                                <dd class="truncate font-medium text-slate-700">{{ $activo->Ubicacion ?? '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-slate-400">Asignado a</dt>
                                <dd class="truncate font-medium text-slate-700">{{ $activo->AsignadoA ?? '—' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3">
                            <a href="{{ route('activos.show', $activo) }}" class="link text-sm">Ver</a>
                            @if (auth()->user()->puedeEscribir())
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('activos.edit', $activo) }}" class="link text-sm">Editar</a>
                                    @if ($activo->estadoCatalogo?->Grupo !== 'Desincorporado')
                                        <button type="button" class="text-sm font-medium text-rose-600 hover:underline"
                                                @click="$dispatch('open-modal', 'desincorporar-activo'); $dispatch('set-desincorporar', {{ $activo->id }})">
                                            Desincorporar
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
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
