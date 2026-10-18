<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$personal->NombreCompleto"
                       :subtitle="$personal->Cargo ? $personal->Cargo . ' · ' . $personal->Nombre_usuario : $personal->Nombre_usuario"
                       icon="user-circle">
            <x-slot name="actions">
                @if ($personal->Estado === 'Activo')
                    <x-badge color="emerald" :dot="true">Activo</x-badge>
                @else
                    <x-badge color="slate" :dot="true">{{ $personal->Estado }}</x-badge>
                @endif
                <a href="{{ route('personal.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('personal.edit', $personal) }}" class="btn btn-primary">
                        <x-icon name="pencil" :size="18" /> Editar
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos</h3>
            </div>
            <dl class="card-body space-y-4 text-sm">
                <div>
                    <dt class="text-slate-500">Nombre completo</dt>
                    <dd class="font-medium text-slate-900">{{ $personal->NombreCompleto }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Nombre de usuario</dt>
                    <dd class="font-medium text-slate-900">{{ $personal->Nombre_usuario }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Cargo</dt>
                    <dd class="font-medium text-slate-900">{{ $personal->Cargo ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Departamento / Ubicación</dt>
                    <dd class="font-medium text-slate-900">{{ $personal->ubicacion?->Nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Observaciones</dt>
                    <dd class="whitespace-pre-line font-medium text-slate-900">{{ $personal->Observaciones ?? '—' }}</dd>
                </div>

                @if (auth()->user()->puedeEscribir() && $personal->Estado === 'Activo')
                    <div class="border-t border-slate-200 pt-4">
                        <form method="POST" action="{{ route('personal.destroy', $personal) }}"
                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Dar de baja a '.$personal->NombreCompleto.'?') }});">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger w-full">
                                <x-icon name="inbox" :size="18" /> Dar de baja
                            </button>
                        </form>
                        <p class="mt-2 text-xs text-slate-400">La baja es lógica: el historial se conserva.</p>
                    </div>
                @endif
            </dl>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">Equipos asignados (activos)</h3>
                <x-badge color="brand">{{ $equipos->count() }}</x-badge>
            </div>
            @if ($equipos->isEmpty())
                <x-empty-state title="Sin equipos asignados" description="Esta persona no tiene equipos asignados actualmente." icon="cube" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Etiqueta</th>
                                <th>Equipo</th>
                                <th>Asignado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipos as $asignacion)
                                <tr>
                                    <td class="font-medium">
                                        <a class="link" href="{{ route('activos.show', $asignacion->fk_Activo) }}">
                                            {{ $asignacion->activo?->Etiqueta_activo }}
                                        </a>
                                    </td>
                                    <td>{{ $asignacion->activo?->Nombre_de_activo }}</td>
                                    <td>{{ $asignacion->Fecha_asignacion?->format('d/m/Y') }}</td>
                                    <td><x-status-badge :grupo="$asignacion->activo?->estadoCatalogo?->Grupo" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
