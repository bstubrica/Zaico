<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Equipos</h2>
            @if (auth()->user()->puedeEscribir())
                <a href="{{ route('activos.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    + Registrar equipo
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            {{-- Filtros --}}
            <form method="GET" action="{{ route('activos.index') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label for="termino" class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                    <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                           placeholder="Nombre, serial, etiqueta, modelo"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="categoria" class="block text-xs font-medium text-gray-600 mb-1">Categoría</label>
                    <select id="categoria" name="categoria"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria }}" @selected(($filtros['categoria'] ?? '') === $categoria)>
                                {{ $categoria }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="estado" class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                    <select id="estado" name="estado"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos</option>
                        @foreach ($grupos as $grupo)
                            <option value="{{ $grupo }}" @selected(($filtros['estado'] ?? '') === $grupo)>
                                {{ $grupo }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asignado_a" class="block text-xs font-medium text-gray-600 mb-1">Asignado a</label>
                    <input type="text" id="asignado_a" name="asignado_a" value="{{ $filtros['asignado_a'] ?? '' }}"
                           placeholder="Nombre de la persona"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Filtrar
                    </button>
                    <a href="{{ route('activos.index') }}"
                       class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Limpiar
                    </a>
                </div>
            </form>

            {{-- Listado --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($activos->isEmpty())
                    <div class="p-8 text-center">
                        <p class="text-gray-600">No hay equipos que coincidan con la búsqueda.</p>
                        @if (auth()->user()->puedeEscribir())
                            <a href="{{ route('activos.create') }}"
                               class="mt-3 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                                Registrar equipo
                            </a>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Etiqueta</th>
                                    <th class="px-4 py-3">Nombre</th>
                                    <th class="px-4 py-3 hidden md:table-cell">Serial</th>
                                    <th class="px-4 py-3 hidden md:table-cell">Categoría</th>
                                    <th class="px-4 py-3 hidden lg:table-cell">Ubicación</th>
                                    <th class="px-4 py-3 hidden lg:table-cell">Asignado a</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($activos as $activo)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <a class="text-indigo-600 hover:underline" href="{{ route('activos.show', $activo) }}">
                                                {{ $activo->Etiqueta_activo }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $activo->Nombre_de_activo }}</td>
                                        <td class="px-4 py-3 text-gray-500 hidden md:table-cell">{{ $activo->Serial ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500 hidden md:table-cell">{{ $activo->Categoria ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ $activo->Ubicacion ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ $activo->AsignadoA ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" />
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('activos.show', $activo) }}"
                                               class="text-indigo-600 hover:underline text-xs font-medium">Ver</a>
                                            @if (auth()->user()->puedeEscribir())
                                                <a href="{{ route('activos.edit', $activo) }}"
                                                   class="ms-3 text-indigo-600 hover:underline text-xs font-medium">Editar</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $activos->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
