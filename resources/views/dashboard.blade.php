<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Contadores por grupo de estado --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Total equipos</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ $total }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Asignados</div>
                    <div class="mt-1 text-3xl font-bold text-blue-700">{{ $porGrupo['Asignado'] ?? 0 }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Disponibles</div>
                    <div class="mt-1 text-3xl font-bold text-green-700">{{ $porGrupo['Disponible'] ?? 0 }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">En reparación / defectuosos</div>
                    <div class="mt-1 text-3xl font-bold text-amber-700">
                        {{ ($porGrupo['Reparacion'] ?? 0) + ($porGrupo['Defectuoso'] ?? 0) }}
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Desincorporados</div>
                    <div class="mt-1 text-3xl font-bold text-red-700">{{ $porGrupo['Desincorporado'] ?? 0 }}</div>
                </div>
            </div>

            {{-- Últimos activos registrados --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800">Últimos equipos registrados</h3>
                    @if (auth()->user()->puedeEscribir())
                        <a href="{{ route('activos.create') }}"
                           class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            + Registrar equipo
                        </a>
                    @endif
                </div>

                @empty($recientes)
                    <div class="p-6 text-center text-sm text-gray-500">
                        <p>Aún no hay equipos registrados.</p>
                        @if (auth()->user()->puedeEscribir())
                            <a href="{{ route('activos.create') }}"
                               class="mt-2 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                                Registrar el primero
                            </a>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Etiqueta</th>
                                    <th class="px-6 py-3">Nombre</th>
                                    <th class="px-6 py-3 hidden md:table-cell">Categoría</th>
                                    <th class="px-6 py-3 hidden md:table-cell">Ubicación</th>
                                    <th class="px-6 py-3 hidden lg:table-cell">Asignado a</th>
                                    <th class="px-6 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($recientes as $activo)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 font-medium text-gray-900">
                                            <a class="text-indigo-600 hover:underline" href="{{ route('activos.show', $activo) }}">
                                                {{ $activo->Etiqueta_activo }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700">{{ $activo->Nombre_de_activo }}</td>
                                        <td class="px-6 py-3 text-gray-500 hidden md:table-cell">{{ $activo->Categoria ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-500 hidden md:table-cell">{{ $activo->Ubicacion ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-500 hidden lg:table-cell">
                                            {{ $activo->asignacionActiva?->personal?->NombreCompleto ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3">
                                            <x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endempty
            </div>
        </div>
    </div>
</x-app-layout>
