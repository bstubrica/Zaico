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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre, apellido, usuario, cargo" class="form-control">
            </div>
            <div>
                <label for="ubicacion_id" class="form-label">Departamento / Ubicación</label>
                <select id="ubicacion_id" name="ubicacion_id" class="form-control">
                    <option value="">Todos</option>
                    <option value="sin_ubicacion" @selected(($filtros['ubicacion_id'] ?? '') === 'sin_ubicacion')>Sin asignar</option>
                    @foreach ($ubicaciones as $ubicacion)
                        <option value="{{ $ubicacion->id }}" @selected(($filtros['ubicacion_id'] ?? '') == $ubicacion->id)>
                            {{ $ubicacion->Nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cargo" class="form-label">Cargo</label>
                <select id="cargo" name="cargo" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($cargos as $cargoOpcion)
                        <option value="{{ $cargoOpcion }}" @selected(($filtros['cargo'] ?? '') === $cargoOpcion)>
                            {{ $cargoOpcion }}
                        </option>
                    @endforeach
                </select>
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

    <div class="card" x-data="vistaListado({ clave: 'vista:personal' })">
        <div x-data="seleccionLote({ clave: 'sel:personal', total: {{ $personas->total() }}, pagina: @js($personas->pluck('id')->all()), mensaje: 'Se darán de baja {n} personas. No podrán recibir equipos.', mensajeGrande: 'Son {n} personas. La baja se aplicará de una sola vez a todas las seleccionadas. ¿Continuar?' })">
            <div class="card-header">
                <h3 class="card-title">
                    Resultados
                    <span class="ms-1 font-normal text-slate-400">({{ $personas->total() }})</span>
                </h3>
                @unless ($personas->isEmpty())
                    <x-vista-toggle />
                @endunless
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
            <div class="table-wrap" x-show="vista === 'tabla'" x-cloak>
                <table class="table">
                    <thead>
                        <tr>
                            @if (auth()->user()->puedeEscribir())
                                <th class="w-10">
                                    <x-sel-todos />
                                </th>
                            @endif
                            <th>Nombre completo</th>
                            <th>Usuario</th>
                            <th class="hidden lg:table-cell">Departamento / Ubicación</th>
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
                                        <x-sel-fila :valor="$persona->id" :etiqueta="$persona->NombreCompleto" />
                                    </td>
                                @endif
                                <td class="font-medium">
                                    <a class="link" href="{{ route('personal.show', $persona) }}">{{ $persona->NombreCompleto }}</a>
                                </td>
                                <td class="text-slate-500">{{ $persona->Nombre_usuario }}</td>
                                <td class="hidden text-slate-500 lg:table-cell">{{ $persona->ubicacion?->Nombre ?? '—' }}</td>
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
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Dar de baja a '.$persona->NombreCompleto.'?') }})">
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

            {{-- Vista de tarjetas --}}
            <div x-show="vista === 'tarjeta'" x-cloak class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($personas as $persona)
                    <div class="group relative flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:shadow-md">
                        @if (auth()->user()->puedeEscribir())
                            <label class="absolute right-3 top-3 z-10 inline-flex cursor-pointer items-center justify-center rounded-md bg-white/90 p-1 shadow-sm ring-1 ring-slate-200">
                                <input type="checkbox" value="{{ $persona->id }}"
                                       class="h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                       :checked="estaSeleccionado({{ $persona->id }})"
                                       @change="alternar({{ $persona->id }}, $event.target.checked)"
                                       aria-label="Seleccionar {{ $persona->NombreCompleto }}">
                            </label>
                        @endif

                        <div class="flex items-start gap-3 pe-6">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">
                                {{ mb_strtoupper(mb_substr($persona->Nombre ?? '', 0, 1).mb_substr($persona->Apellido ?? '', 0, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('personal.show', $persona) }}"
                                   class="block truncate font-semibold text-slate-900 hover:text-brand-700">{{ $persona->NombreCompleto }}</a>
                                <p class="truncate text-xs text-slate-500">{{ $persona->Nombre_usuario }}</p>
                                <p class="mt-0.5 truncate text-sm text-slate-600">{{ $persona->Cargo ?? '—' }}</p>
                                @if ($persona->ubicacion)
                                    <p class="truncate text-xs text-slate-400">{{ $persona->ubicacion->Nombre }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($persona->Estado === 'Activo')
                                <x-badge color="emerald" :dot="true">Activo</x-badge>
                            @else
                                <x-badge color="slate" :dot="true">{{ $persona->Estado }}</x-badge>
                            @endif
                            <x-badge :color="$persona->asignaciones_activas > 0 ? 'sky' : 'slate'">
                                {{ $persona->asignaciones_activas }} equipo(s)
                            </x-badge>
                        </div>

                        <div class="mt-4 flex flex-1 items-end justify-between gap-2 border-t border-slate-100 pt-3">
                            <a href="{{ route('personal.show', $persona) }}" class="link text-sm">Ver</a>
                            @if (auth()->user()->puedeEscribir())
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('personal.edit', $persona) }}" class="link text-sm">Editar</a>
                                    <form method="POST" action="{{ route('personal.destroy', $persona) }}" class="inline"
                                          onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Dar de baja a '.$persona->NombreCompleto.'?') }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">Baja</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
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
    </div>

    <x-resultado-lote />
</x-app-layout>
