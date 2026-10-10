<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Detalle de importación" :subtitle="$importacion->Nombre_archivo" icon="document">
            <x-slot name="actions">
                <x-badge :color="$importacion->Modo === 'DryRun' ? 'slate' : 'brand'">{{ $importacion->Modo }}</x-badge>
                <a href="{{ route('importaciones.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat-card label="Total filas" :value="$importacion->Total_filas" icon="clipboard" color="slate" />
        <x-stat-card label="Nuevas" :value="$importacion->Insertadas" icon="plus" color="emerald" />
        <x-stat-card label="Actualizadas" :value="$importacion->Actualizadas" icon="refresh" color="sky" />
        <x-stat-card label="Errores" :value="$importacion->Errores" icon="warning" :color="$importacion->Errores > 0 ? 'rose' : 'slate'" />
        <x-stat-card label="Archivo" :value="$importacion->Estado" icon="document" color="brand" />
    </div>

    @if ($importacion->Modo === 'DryRun')
        <x-alert tipo="info">
            Este fue una <strong>previsualización</strong>: no se modificó la base de datos.
            Vuelva a la pantalla anterior para confirmar la importación.
        </x-alert>
    @endif

    <div class="card" x-data="{ soloErrores: false }">
        <div class="card-header">
            <h3 class="card-title">Filas</h3>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" x-model="soloErrores" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Solo errores
            </label>
        </div>

        @if ($importacion->detalles->isEmpty())
            <x-empty-state title="Sin detalle de filas" icon="clipboard" />
        @else
            <div class="table-wrap max-h-[32rem] overflow-y-auto">
                <table class="table">
                    <thead class="sticky top-0">
                        <tr>
                            <th>Fila</th>
                            <th>Etiqueta</th>
                            <th>Acción</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($importacion->detalles as $detalle)
                            @php
                                $colores = [
                                    'Insertado' => 'emerald',
                                    'Actualizado' => 'sky',
                                    'SinCambios' => 'slate',
                                    'Error' => 'rose',
                                ];
                            @endphp
                            <tr x-show="!soloErrores || '{{ $detalle->Accion }}' === 'Error'"
                                @class(['bg-rose-50/60' => $detalle->Accion === 'Error'])>
                                <td class="text-slate-500">{{ $detalle->Fila_numero }}</td>
                                <td class="font-medium text-slate-900">{{ $detalle->Etiqueta ?? '—' }}</td>
                                <td><x-badge :color="$colores[$detalle->Accion] ?? 'slate'">{{ $detalle->Accion }}</x-badge></td>
                                <td class="text-slate-600">{{ $detalle->Mensaje ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
