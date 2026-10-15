<x-print-layout title="{{ $titulo }} — {{ $folio }}">
    <div class="mx-auto max-w-4xl p-6 md:p-10">
        <div class="carta mx-auto w-full rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-6 sm:px-10">
                <div class="space-y-2">
                    @php
                        $org = $organizacion ?? [];
                    @endphp

                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
                            <x-icon name="cube" :size="26" />
                        </span>
                        <div>
                            <h1 class="text-lg font-bold text-slate-900">{{ $org['nombre'] ?? config('app.name') }}</h1>
                            <div class="mt-1 text-xs text-slate-500">
                                @foreach (array_filter([$org['rif'] ?? null, $org['direccion'] ?? null, $org['ciudad'] ?? null]) as $linea)
                                    <p>{{ $linea }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <h2 class="text-base font-semibold uppercase tracking-wider text-slate-800">{{ $titulo }}</h2>
                    <p class="mt-1 text-xs text-slate-500">Folio: <span class="font-semibold text-slate-800">{{ $folio }}</span></p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Emitida el {{ $emitida_en->format('d/m/Y') }} a las {{ $emitida_en->format('H:i') }}
                        @if ($emitida_por)
                            · Por: {{ $emitida_por }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($modo === 'individual' && $baja !== null)
                <div class="grid grid-cols-1 gap-4 px-6 py-6 sm:grid-cols-2 sm:px-10">
                    <div class="rounded-xl border border-slate-200">
                        <dl class="divide-y divide-slate-100">
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Tipo de baja</dt>
                                <dd class="col-span-2 font-medium text-slate-900">{{ $baja['tipo_etiqueta'] }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Destinatario</dt>
                                <dd class="col-span-2 text-slate-900">{{ $baja['destinatario'] ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Detalle</dt>
                                <dd class="col-span-2 text-slate-900">{{ $baja['detalle_destino'] ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Referencia</dt>
                                <dd class="col-span-2 text-slate-900">{{ $baja['referencia'] ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-xl border border-slate-200">
                        <dl class="divide-y divide-slate-100">
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Fecha</dt>
                                <dd class="col-span-2 text-slate-900">{{ $baja['fecha']?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Monto</dt>
                                <dd class="col-span-2 text-slate-900">{{ $baja['monto_texto'] ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Registrado por</dt>
                                <dd class="col-span-2 text-slate-900">{{ $baja['registrado_por'] ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-3 gap-2 px-4 py-2 text-sm">
                                <dt class="text-slate-500">Estado</dt>
                                <dd class="col-span-2 text-slate-900">
                                    {{ $baja['estado'] }}
                                    @if ($baja['anulada'])
                                        <span class="ms-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">ANULADA</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                @if ($baja['motivo'])
                    <div class="px-6 sm:px-10">
                        <div class="rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">Motivo</p>
                            <p class="mt-1 whitespace-pre-line leading-relaxed">{{ $baja['motivo'] }}</p>
                        </div>
                    </div>
                @endif
            @else
                <div class="px-6 py-5 sm:px-10">
                    <p class="text-sm text-slate-700">
                        <span class="font-semibold text-slate-900">Detalle del acta:</span> {{ $filtros_resumen ?? 'Todos los equipos desincorporados.' }}
                    </p>
                </div>
            @endif

            <div class="px-6 py-5 sm:px-10">
                <h3 class="text-base font-semibold text-slate-900">Equipos desincorporados</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Etiqueta</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Equipo</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Serial</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Categoría</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Modelo</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Ubicación</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Fecha</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Destinatario</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($equipos as $equipo)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">{{ $equipo['etiqueta'] }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $equipo['nombre'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $equipo['serial'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $equipo['categoria'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $equipo['modelo'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $equipo['ubicacion'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $equipo['fecha']?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $equipo['destinatario'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">
                                        @if ($equipo['sin_datos'])
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Sin datos de baja</span>
                                        @else
                                            {{ $equipo['estado'] }}
                                            @if ($equipo['anulada'])
                                                <span class="ms-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">ANULADA</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-4 text-center text-sm text-slate-500">No hay equipos en esta carta.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm font-medium text-slate-700">Total de equipos: {{ count($equipos) }}</p>
            </div>

            <div class="flex flex-col gap-6 px-6 pb-8 sm:flex-row sm:justify-between sm:px-10">
                @php
                    $firmas = $firmas ?? [];
                    $orgNombre = $org['nombre'] ?? config('app.name');
                @endphp
                <div class="flex-1 space-y-2">
                    <div class="h-12 border-b border-slate-400"></div>
                    <p class="text-sm font-semibold text-slate-900">{{ $firmas['entrega'] ?? 'Entrega' }}</p>
                    @if ($modo === 'individual' && $baja !== null)
                        <p class="text-sm text-slate-600">{{ $baja['registrado_por'] ?? $orgNombre }}</p>
                    @else
                        <p class="text-sm text-slate-600">{{ $emitida_por ?? $orgNombre }}</p>
                    @endif
                </div>
                <div class="flex-1 space-y-2 text-right sm:text-left">
                    <div class="h-12 border-b border-slate-400"></div>
                    <p class="text-sm font-semibold text-slate-900">{{ $firmas['recibe'] ?? 'Recibe' }}</p>
                    @if ($modo === 'individual' && $baja !== null)
                        <p class="text-sm text-slate-600">{{ $baja['destinatario'] ?? '—' }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="no-print mt-5 flex flex-wrap items-center justify-center gap-2">
            <a href="{{ request()->fullUrl() }}" class="btn btn-neutral" onclick="window.print(); return false;">
                <x-icon name="download" :size="18" /> Guardar como PDF (Imprimir)
            </a>
            <a href="{{ route('desincorporados.index', request()->except(['formato'])) }}" class="btn btn-ghost">Volver al listado</a>
        </div>
    </div>
</x-print-layout>
