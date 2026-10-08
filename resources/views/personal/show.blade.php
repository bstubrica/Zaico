<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $personal->NombreCompleto }}</h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $personal->Estado === 'Activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                    {{ $personal->Estado }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('personal.index') }}"
                   class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver</a>
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('personal.edit', $personal) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        Editar
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Datos</h3>
                </div>
                <dl class="p-6 text-sm space-y-4">
                    <div>
                        <dt class="text-gray-500">Nombre completo</dt>
                        <dd class="font-medium text-gray-900">{{ $personal->NombreCompleto }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Nombre de usuario</dt>
                        <dd class="font-medium text-gray-900">{{ $personal->Nombre_usuario }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Cargo</dt>
                        <dd class="font-medium text-gray-900">{{ $personal->Cargo ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Observaciones</dt>
                        <dd class="font-medium text-gray-900 whitespace-pre-line">{{ $personal->Observaciones ?? '—' }}</dd>
                    </div>

                    @if (auth()->user()->puedeEscribir() && $personal->Estado === 'Activo')
                        <div class="pt-4 border-t border-gray-100">
                            <form method="POST" action="{{ route('personal.destroy', $personal) }}"
                                  onsubmit="return confirm('¿Dar de baja a {{ $personal->NombreCompleto }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="w-full rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                                    Dar de baja
                                </button>
                            </form>
                            <p class="mt-2 text-xs text-gray-400">La baja es lógica: el historial se conserva.</p>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="lg:col-span-2 bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Equipos asignados (activos)</h3>
                </div>
                @if ($equipos->isEmpty())
                    <div class="p-6 text-sm text-gray-500 text-center">Sin equipos asignados actualmente.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Etiqueta</th>
                                    <th class="px-6 py-3">Equipo</th>
                                    <th class="px-6 py-3">Asignado</th>
                                    <th class="px-6 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($equipos as $asignacion)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 font-medium text-gray-900">
                                            <a class="text-indigo-600 hover:underline"
                                               href="{{ route('activos.show', $asignacion->fk_Activo) }}">
                                                {{ $asignacion->activo?->Etiqueta_activo }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700">{{ $asignacion->activo?->Nombre_de_activo }}</td>
                                        <td class="px-6 py-3 text-gray-700">{{ $asignacion->Fecha_asignacion?->format('d/m/Y') }}</td>
                                        <td class="px-6 py-3">
                                            <x-status-badge :grupo="$asignacion->activo?->estadoCatalogo?->Grupo" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
