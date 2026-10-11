@php
    use App\Models\Alerta;

    $filtros = [
        null => 'Todas',
        Alerta::TIPO_REPARACION => 'Reparación',
        Alerta::TIPO_GARANTIA => 'Garantía',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Alertas" subtitle="Reparaciones prolongadas y garantías por vencer" icon="bell">
            <x-slot name="actions">
                @if (auth()->user()->puedeEscribir())
                    <form method="POST" action="{{ route('alertas.revisar') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <x-icon name="refresh" :size="18" /> Revisar ahora
                        </button>
                    </form>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-stat-card label="Reparación prolongada" :value="$resumen[Alerta::TIPO_REPARACION] ?? 0" icon="wrench" color="amber" />
        <x-stat-card label="Garantía por vencer" :value="$resumen[Alerta::TIPO_GARANTIA] ?? 0" icon="currency" color="rose" />
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach ($filtros as $valor => $etiqueta)
            <a href="{{ route('alertas.index', $valor ? ['tipo' => $valor] : []) }}"
               class="btn btn-sm {{ $tipo === $valor ? 'btn-neutral' : 'btn-ghost' }}">
                {{ $etiqueta }}
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Alertas activas
                <span class="ms-1 font-normal text-slate-400">({{ $alertas->total() }})</span>
            </h3>
        </div>

        @if ($alertas->isEmpty())
            <x-empty-state title="Sin alertas activas"
                           description="No hay reparaciones prolongadas ni garantías próximas a vencer."
                           icon="check-circle" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Activo</th>
                            <th>Tipo</th>
                            <th>Detalle</th>
                            <th class="hidden md:table-cell">Fecha</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($alertas as $alerta)
                            <tr>
                                <td>
                                    <a class="link font-medium" href="{{ route('activos.show', $alerta->fk_activo) }}">
                                        {{ $alerta->activo?->Etiqueta_activo ?? '—' }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $alerta->activo?->Nombre_de_activo }}</p>
                                </td>
                                <td>
                                    <x-badge :color="$alerta->Tipo === Alerta::TIPO_REPARACION ? 'amber' : 'rose'" dot>
                                        {{ $alerta->tipo_label }}
                                    </x-badge>
                                </td>
                                <td class="text-slate-600">{{ $alerta->Mensaje }}</td>
                                <td class="hidden text-slate-500 md:table-cell">
                                    {{ $alerta->Fecha_referencia?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('activos.show', $alerta->fk_activo) }}" class="link">Ver equipo</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $alertas->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
