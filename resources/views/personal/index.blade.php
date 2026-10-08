<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Personal</h2>
            @if (auth()->user()->puedeEscribir())
                <a href="{{ route('personal.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    + Registrar persona
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('personal.index') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2">
                    <label for="termino" class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                    <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                           placeholder="Nombre, apellido, usuario, cargo"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="estado" class="block text-xs font-medium text-gray-600 mb-1">Estado</label>
                    <select id="estado" name="estado"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos</option>
                        <option value="Activo" @selected(($filtros['estado'] ?? '') === 'Activo')>Activo</option>
                        <option value="Inactivo" @selected(($filtros['estado'] ?? '') === 'Inactivo')>Inactivo</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Filtrar
                    </button>
                    <a href="{{ route('personal.index') }}"
                       class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Limpiar
                    </a>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($personas->isEmpty())
                    <div class="p-8 text-center">
                        <p class="text-gray-600">No hay personas registradas.</p>
                        @if (auth()->user()->puedeEscribir())
                            <a href="{{ route('personal.create') }}"
                               class="mt-3 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                                Registrar persona
                            </a>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Nombre completo</th>
                                    <th class="px-4 py-3">Usuario</th>
                                    <th class="px-4 py-3 hidden md:table-cell">Cargo</th>
                                    <th class="px-4 py-3 text-center">Equipos asignados</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($personas as $persona)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <a class="text-indigo-600 hover:underline" href="{{ route('personal.show', $persona) }}">
                                                {{ $persona->NombreCompleto }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ $persona->Nombre_usuario }}</td>
                                        <td class="px-4 py-3 text-gray-500 hidden md:table-cell">{{ $persona->Cargo ?? '—' }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center justify-center rounded-full {{ $persona->asignaciones_activas > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }} px-2.5 py-0.5 text-xs font-medium">
                                                {{ $persona->asignaciones_activas }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $persona->Estado === 'Activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $persona->Estado }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('personal.show', $persona) }}"
                                               class="text-indigo-600 hover:underline text-xs font-medium">Ver</a>
                                            @if (auth()->user()->puedeEscribir())
                                                <a href="{{ route('personal.edit', $persona) }}"
                                                   class="ms-3 text-indigo-600 hover:underline text-xs font-medium">Editar</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $personas->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
