<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Historial de asignaciones</h2>
            <span class="text-sm text-gray-500">{{ $asignaciones->total() }} registros</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('historial.index') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label for="desde" class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                    <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="hasta" class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="personal_id" class="block text-xs font-medium text-gray-600 mb-1">Persona</label>
                    <select id="personal_id" name="personal_id"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todas</option>
                        @foreach ($personas as $persona)
                            <option value="{{ $persona->id }}" @selected((int) ($filtros['personal_id'] ?? 0) === $persona->id)>
                                {{ $persona->NombreCompleto }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Filtrar
                    </button>
                    <a href="{{ route('historial.index') }}"
                       class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Limpiar
                    </a>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($asignaciones->isEmpty())
                    <div class="p-8 text-center text-sm text-gray-500">No hay asignaciones registradas.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Fecha asignación</th>
                                    <th class="px-4 py-3">Equipo</th>
                                    <th class="px-4 py-3">Persona</th>
                                    <th class="px-4 py-3 hidden md:table-cell">Devuelto</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 hidden lg:table-cell">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($asignaciones as $asignacion)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-700">{{ $asignacion->Fecha_asignacion?->format('d/m/Y') }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <a class="text-indigo-600 hover:underline"
                                               href="{{ route('activos.show', $asignacion->fk_Activo) }}">
                                                {{ $asignacion->activo?->Etiqueta_activo }}
                                            </a>
                                            <span class="block text-xs text-gray-500">{{ $asignacion->activo?->Nombre_de_activo }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $asignacion->personal?->NombreCompleto ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700 hidden md:table-cell">{{ $asignacion->Fecha_devolucion?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $asignacion->Estado === 'Activa' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $asignacion->Estado }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ \Illuminate\Support\Str::limit($asignacion->Observaciones ?? '—', 60) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $asignaciones->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
