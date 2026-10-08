<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $activo->Nombre_de_activo }}</h2>
                <x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" />
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('activos.index') }}"
                   class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver</a>
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('activos.edit', $activo) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        Editar
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Datos del equipo --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-800">Datos del equipo</h3>
                    </div>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 p-6 text-sm">
                        <div>
                            <dt class="text-gray-500">Etiqueta</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->Etiqueta_activo }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Serial</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->Serial ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Categoría</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->Categoria ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fabricante / Modelo</dt>
                            <dd class="font-medium text-gray-900">
                                {{ trim(($activo->Fabricante ?? '—').' / '.($activo->Modelo ?? '—')) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Ubicación</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->Ubicacion ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Ubicación predeterminada</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->Ubicacion_Predeterminada ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Dirección MAC</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->Direccion_MAC ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Estado de catálogo</dt>
                            <dd class="font-medium text-gray-900">{{ $activo->estadoCatalogo?->Nombre ?? '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Observaciones</dt>
                            <dd class="font-medium text-gray-900 whitespace-pre-line">{{ $activo->Observaciones ?? '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2 border-t border-gray-100 pt-4 text-xs text-gray-400">
                            Registrado el {{ $activo->Creado_el?->format('d/m/Y H:i') }}
                        </div>
                    </dl>
                </div>

                {{-- Historial de asignaciones --}}
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-800">Historial de asignaciones</h3>
                    </div>
                    @if ($historial->isEmpty())
                        <div class="p-6 text-sm text-gray-500 text-center">Este equipo nunca ha sido asignado.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-6 py-3">Persona</th>
                                        <th class="px-6 py-3">Asignado</th>
                                        <th class="px-6 py-3">Devuelto</th>
                                        <th class="px-6 py-3">Estado</th>
                                        <th class="px-6 py-3 hidden md:table-cell">Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($historial as $asignacion)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-3 font-medium text-gray-900">
                                                {{ $asignacion->personal?->NombreCompleto ?? '—' }}
                                            </td>
                                            <td class="px-6 py-3 text-gray-700">{{ $asignacion->Fecha_asignacion?->format('d/m/Y') }}</td>
                                            <td class="px-6 py-3 text-gray-700">{{ $asignacion->Fecha_devolucion?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="px-6 py-3">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $asignacion->Estado === 'Activa' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $asignacion->Estado }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 text-gray-500 hidden md:table-cell">{{ $asignacion->Observaciones ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Panel lateral de acciones --}}
            <div class="space-y-6">
                @auth
                    @if (auth()->user()->puedeEscribir())
                        @if ($activo->asignacionActiva)
                            {{-- Devolución --}}
                            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                                <h3 class="text-base font-semibold text-gray-800 mb-1">Devolver equipo</h3>
                                <p class="text-sm text-gray-500 mb-4">
                                    Asignado a: <span class="font-medium text-gray-800">{{ $activo->asignacionActiva->personal?->NombreCompleto }}</span>
                                </p>

                                <form method="POST" action="{{ route('activos.devolver', $activo) }}">
                                    @csrf

                                    <label for="Fecha_devolucion" class="block text-xs font-medium text-gray-600 mb-1">Fecha de devolución *</label>
                                    <input type="date" id="Fecha_devolucion" name="Fecha_devolucion" required
                                           value="{{ old('Fecha_devolucion', now()->toDateString()) }}"
                                           class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                                    <label for="Observaciones" class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                                    <textarea id="Observaciones" name="Observaciones" rows="2"
                                              class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>

                                    <button type="submit"
                                            class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                        Registrar devolución
                                    </button>
                                </form>
                            </div>
                        @elseif ($activo->estadoCatalogo?->Grupo !== 'Desincorporado')
                            {{-- Asignación --}}
                            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                                <h3 class="text-base font-semibold text-gray-800 mb-4">Asignar equipo</h3>

                                @if ($personas->isEmpty())
                                    <p class="text-sm text-gray-500">No hay personas activas registradas.
                                        <a href="{{ route('personal.create') }}" class="text-indigo-600 hover:underline">Registrar persona</a>
                                    </p>
                                @else
                                    <form method="POST" action="{{ route('activos.asignar', $activo) }}">
                                        @csrf

                                        <label for="fk_Personal" class="block text-xs font-medium text-gray-600 mb-1">Asignar a *</label>
                                        <select id="fk_Personal" name="fk_Personal" required
                                                class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">Seleccione una persona…</option>
                                            @foreach ($personas as $persona)
                                                <option value="{{ $persona->id }}">{{ $persona->NombreCompleto }} ({{ $persona->Cargo ?? $persona->Nombre_usuario }})</option>
                                            @endforeach
                                        </select>

                                        <label for="Fecha_asignacion" class="block text-xs font-medium text-gray-600 mb-1">Fecha de asignación *</label>
                                        <input type="date" id="Fecha_asignacion" name="Fecha_asignacion" required
                                               value="{{ old('Fecha_asignacion', now()->toDateString()) }}"
                                               class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                                        <label for="Observaciones_asig" class="block text-xs font-medium text-gray-600 mb-1">Observaciones</label>
                                        <textarea id="Observaciones_asig" name="Observaciones" rows="2"
                                                  class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>

                                        <button type="submit"
                                                class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            Asignar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif

                        {{-- Cambio de estado --}}
                        <div class="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-base font-semibold text-gray-800 mb-4">Cambiar estado</h3>

                            <form method="POST" action="{{ route('activos.estado', $activo) }}">
                                @csrf
                                @method('PUT')

                                <label for="fk_estado" class="block text-xs font-medium text-gray-600 mb-1">Nuevo estado *</label>
                                <select id="fk_estado" name="fk_estado" required
                                        class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($estados as $estado)
                                        <option value="{{ $estado->id }}" @selected($activo->fk_estado === $estado->id)>
                                            {{ $estado->Grupo }} · {{ $estado->Nombre }}
                                        </option>
                                    @endforeach
                                </select>

                                <label for="motivo" class="block text-xs font-medium text-gray-600 mb-1">Motivo *</label>
                                <textarea id="motivo" name="motivo" rows="2" required
                                          class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="Justificación del cambio de estado">{{ old('motivo') }}</textarea>

                                <button type="submit"
                                        class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Aplicar cambio
                                </button>
                            </form>
                        </div>
                    @endif
                @endauth

                {{-- Mantenimientos --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-base font-semibold text-gray-800">Mantenimientos</h3>
                        @if ($activo->mantenimientos->isNotEmpty())
                            <a href="{{ route('activos.mantenimientos', $activo) }}"
                               class="text-xs font-medium text-indigo-600 hover:underline">Ver todos</a>
                        @endif
                    </div>

                    @if ($activo->mantenimientos->isEmpty())
                        <p class="text-sm text-gray-500">Sin mantenimientos registrados.</p>
                    @else
                        <ul class="text-sm divide-y divide-gray-100 mb-4">
                            @foreach ($activo->mantenimientos->take(3) as $mantenimiento)
                                <li class="py-2">
                                    <span class="font-medium text-gray-800">{{ $mantenimiento->Tipo }}</span>
                                    · {{ $mantenimiento->Fecha_mantenimiento?->format('d/m/Y') }}
                                    <p class="text-gray-500 text-xs mt-0.5">{{ \Illuminate\Support\Str::limit($mantenimiento->Descripcion, 80) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (auth()->user()->puedeEscribir())
                        <form method="POST" action="{{ route('activos.mantenimientos.store', $activo) }}"
                              enctype="multipart/form-data"
                              class="mt-4 border-t border-gray-100 pt-4">
                            @csrf

                            <h4 class="text-sm font-semibold text-gray-800 mb-3">Registrar mantenimiento</h4>

                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label for="Tipo" class="block text-xs font-medium text-gray-600 mb-1">Tipo *</label>
                                    <select id="Tipo" name="Tipo" required
                                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="Preventivo">Preventivo</option>
                                        <option value="Correctivo">Correctivo</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="Fecha_mantenimiento" class="block text-xs font-medium text-gray-600 mb-1">Fecha *</label>
                                    <input type="date" id="Fecha_mantenimiento" name="Fecha_mantenimiento" required
                                           value="{{ old('Fecha_mantenimiento', now()->toDateString()) }}"
                                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <label for="Descripcion" class="block text-xs font-medium text-gray-600 mb-1">Descripción *</label>
                            <textarea id="Descripcion" name="Descripcion" rows="2" required
                                      class="mb-3 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Trabajo realizado, repuestos, hallazgos…">{{ old('Descripcion') }}</textarea>

                            <label for="evidencias" class="block text-xs font-medium text-gray-600 mb-1">
                                Evidencias (JPG, PNG, PDF, MP4 · máx. 20 MB c/u)
                            </label>
                            <input type="file" id="evidencias" name="evidencias[]" multiple
                                   accept=".jpg,.jpeg,.png,.pdf,.mp4"
                                   class="mb-3 block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200">

                            <button type="submit"
                                    class="w-full rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500">
                                Guardar mantenimiento
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
