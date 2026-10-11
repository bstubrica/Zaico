<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Mantenimientos" :subtitle="$activo->Etiqueta_activo . ' · ' . $activo->Nombre_de_activo" icon="wrench">
            <x-slot name="actions">
                <a href="{{ route('activos.show', $activo) }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver al equipo
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="card">
            @if ($mantenimientos->isEmpty())
                <x-empty-state title="Sin mantenimientos" description="Este equipo no tiene mantenimientos registrados." icon="wrench" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($mantenimientos as $mantenimiento)
                        <li class="p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :color="$mantenimiento->Tipo === 'Preventivo' ? 'sky' : 'amber'">{{ $mantenimiento->Tipo }}</x-badge>
                                <span class="text-sm text-slate-500">
                                    {{ $mantenimiento->Fecha_mantenimiento?->format('d/m/Y') }}
                                    · {{ $mantenimiento->usuario?->name ?? '—' }}
                                </span>
                                @if (auth()->user()->puedeEscribir())
                                    <div class="ms-auto flex items-center gap-1">
                                        <a href="{{ route('mantenimientos.edit', $mantenimiento) }}" class="btn btn-ghost btn-sm" title="Editar">
                                            <x-icon name="pencil" :size="16" />
                                        </a>
                                        <x-delete-form :action="route('mantenimientos.destroy', $mantenimiento)"
                                                       title="Eliminar mantenimiento"
                                                       message="¿Eliminar este mantenimiento? Esta acción no se puede deshacer."
                                                       aria-label="Eliminar mantenimiento">
                                            <x-icon name="trash" :size="16" />
                                        </x-delete-form>
                                    </div>
                                @endif
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $mantenimiento->Descripcion }}</p>

                            @if ($mantenimiento->evidencias->isNotEmpty())
                                <ul class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($mantenimiento->evidencias as $evidencia)
                                        <li>
                                            <a href="{{ route('mantenimientos.evidencia', $evidencia) }}" class="link inline-flex items-center gap-1">
                                                <x-icon name="document" :size="16" /> {{ $evidencia->Nombre_original }}
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
</x-app-layout>
