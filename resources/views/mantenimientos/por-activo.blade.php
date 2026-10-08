<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mantenimientos · {{ $activo->Etiqueta_activo }}
            </h2>
            <a href="{{ route('activos.show', $activo) }}"
               class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver al equipo</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($mantenimientos->isEmpty())
                    <div class="p-8 text-center text-sm text-gray-500">
                        Este equipo no tiene mantenimientos registrados.
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($mantenimientos as $mantenimiento)
                            <li class="p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $mantenimiento->Tipo === 'Preventivo' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $mantenimiento->Tipo }}
                                        </span>
                                        <span class="ms-2 text-sm text-gray-500">
                                            {{ $mantenimiento->Fecha_mantenimiento?->format('d/m/Y') }}
                                            · {{ $mantenimiento->usuario?->name ?? '—' }}
                                        </span>
                                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-line">{{ $mantenimiento->Descripcion }}</p>
                                    </div>
                                </div>

                                @if ($mantenimiento->evidencias->isNotEmpty())
                                    <ul class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($mantenimiento->evidencias as $evidencia)
                                            <li>
                                                <a href="{{ route('mantenimientos.evidencia', $evidencia) }}"
                                                   class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">
                                                    📎 {{ $evidencia->Nombre_original }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
