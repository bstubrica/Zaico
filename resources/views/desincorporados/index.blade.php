<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Equipos desincorporados" subtitle="Bajas del inventario con su tipo, destino y motivo" icon="inbox">
            <x-slot name="actions">
                <x-dropdown align="right" width="64">
                    <x-slot name="trigger">
                        <button class="btn btn-secondary">
                            <x-icon name="download" :size="18" /> Carta del listado
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach (\App\Enums\FormatoCarta::opciones() as $valor => $etiqueta)
                            <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" href="{{ route('desincorporados.carta', array_merge(request()->query(), ['formato' => $valor])) }}">
                                {{ $etiqueta }}
                            </a>
                        @endforeach
                    </x-slot>
                </x-dropdown>
                <a href="{{ route('reportes.desincorporados', request()->query()) }}" class="btn btn-secondary">
                    <x-icon name="download" :size="18" /> Exportar Excel (CSV)
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="GET" action="{{ route('desincorporados.index') }}" class="card card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="termino" class="form-label">Buscar</label>
                <input type="text" id="termino" name="termino" value="{{ $filtros['termino'] ?? '' }}"
                       placeholder="Nombre, serial, etiqueta…" class="form-control">
            </div>
            <div>
                <label for="tipo" class="form-label">Tipo de desincorporación</label>
                <select id="tipo" name="tipo" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($tipos as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(($filtros['tipo'] ?? '') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control">
            </div>
            <div>
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control">
            </div>
            <div class="flex flex-col justify-end gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="sin_datos" value="1" @checked(! empty($filtros['sin_datos']))
                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Solo sin datos de baja
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn btn-neutral flex-1">
                        <x-icon name="funnel" :size="18" /> Filtrar
                    </button>
                    <a href="{{ route('desincorporados.index') }}" class="btn btn-ghost">Limpiar</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                Resultados
                <span class="ms-1 font-normal text-slate-400">({{ $desincorporados->total() }})</span>
            </h3>
        </div>

        @if ($desincorporados->isEmpty())
            <x-empty-state title="No hay equipos desincorporados"
                           description="Los equipos que se den de baja aparecerán aquí, separados del inventario activo." icon="inbox" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Equipo</th>
                            <th class="hidden whitespace-nowrap md:table-cell">Tipo de desincorporación</th>
                            <th class="hidden lg:table-cell">Destinatario / destino</th>
                            <th class="hidden xl:table-cell">Referencia</th>
                            <th class="hidden xl:table-cell">Motivo</th>
                            <th class="hidden md:table-cell">Registrado por</th>
                            @if (auth()->user()->puedeEscribir())
                                <th class="text-right">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($desincorporados as $activo)
                            @php $baja = $activo->desincorporacion; @endphp
                            <tr>
                                <td class="whitespace-nowrap">{{ $baja?->Fecha?->format('d/m/Y') ?? '—' }}</td>
                                <td class="font-medium">
                                    <a class="link" href="{{ route('activos.show', $activo) }}">
                                        {{ $activo->Etiqueta_activo }}
                                    </a>
                                    <span class="block text-xs text-slate-500">{{ $activo->Nombre_de_activo }}</span>
                                </td>
                                <td class="hidden md:table-cell">
                                    @php
                                        $color = match ($baja?->Tipo) {
                                            'Venta' => 'sky',
                                            'Donacion' => 'emerald',
                                            'Transferencia' => 'amber',
                                            'Robo' => 'rose',
                                            default => 'slate',
                                        };
                                    @endphp
                                    <x-badge :color="$color">{{ \App\Enums\TipoDesincorporacion::etiquetaDe($baja?->Tipo) }}</x-badge>
                                </td>
                                <td class="hidden lg:table-cell">
                                    <span class="block text-slate-700">{{ $baja?->Destinatario ?? '—' }}</span>
                                    @if ($baja?->Detalle_destino)
                                        <span class="block text-xs text-slate-500">{{ $baja->Detalle_destino }}</span>
                                    @endif
                                </td>
                                <td class="hidden text-slate-500 xl:table-cell">{{ $baja?->Referencia ?? '—' }}</td>
                                <td class="hidden max-w-xs text-slate-500 xl:table-cell">{{ \Illuminate\Support\Str::limit($baja?->Motivo, 60) ?? '—' }}</td>
                                <td class="hidden text-slate-500 md:table-cell">{{ $baja?->usuario?->name ?? '—' }}</td>
                                @if (auth()->user()->puedeEscribir())
                                    <td class="text-right whitespace-nowrap">
                                        @if ($baja)
                                            <x-dropdown align="right" width="56">
                                                <x-slot name="trigger">
                                                    <button type="button" class="link">Carta</button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    @foreach (\App\Enums\FormatoCarta::opciones() as $valor => $etiqueta)
                                                        <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" href="{{ route('desincorporados.carta.activo', array_merge(['desincorporacion' => $baja->id], ['formato' => $valor])) }}">
                                                            {{ $etiqueta }}
                                                        </a>
                                                    @endforeach
                                                </x-slot>
                                            </x-dropdown>
                                            <button type="button" class="link ms-2"
                                                    @click="$dispatch('open-modal', 'editar-desincorporacion'); $dispatch('set-editar-desincorporacion', @js([
                                                        'id' => $baja->id,
                                                        'tipo' => $baja->Tipo,
                                                        'destinatario' => $baja->Destinatario,
                                                        'detalle_destino' => $baja->Detalle_destino,
                                                        'referencia' => $baja->Referencia,
                                                        'monto' => $baja->Monto,
                                                        'moneda' => $baja->Moneda,
                                                        'fecha' => $baja->Fecha?->format('Y-m-d'),
                                                        'motivo' => $baja->Motivo,
                                                    ]))">
                                                Editar
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('activos.reincorporar', $activo) }}" class="inline"
                                              onsubmit="return confirm('¿Reincorporar este equipo al inventario activo? La baja quedará anulada.')">
                                            @csrf
                                            <button type="submit" class="link ms-2 text-emerald-600">Reincorporar</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $desincorporados->links() }}
            </div>
        @endif
    </div>

    @if (auth()->user()->puedeEscribir())
        <x-modal name="editar-desincorporacion" maxWidth="lg" focusable>
            <div x-data="{
                    id: '',
                    tipo: '',
                    destinatario: '',
                    detalle_destino: '',
                    referencia: '',
                    monto: '',
                    moneda: 'USD',
                    fecha: '',
                    motivo: '',
                    exigeMonto() { return this.tipo === 'Venta'; },
                }"
                @set-editar-desincorporacion.window="
                    id = $event.detail.id ?? '';
                    tipo = $event.detail.tipo ?? '';
                    destinatario = $event.detail.destinatario ?? '';
                    detalle_destino = $event.detail.detalle_destino ?? '';
                    referencia = $event.detail.referencia ?? '';
                    monto = $event.detail.monto ?? '';
                    moneda = $event.detail.moneda ?? 'USD';
                    fecha = $event.detail.fecha ?? '';
                    motivo = $event.detail.motivo ?? '';
                ">
                <form method="POST" :action="'{{ url('desincorporados') }}/' + id" class="p-6">
                    @csrf
                    @method('PUT')
                    <h2 class="text-lg font-semibold text-slate-900">Editar registro de baja</h2>
                    <p class="mt-1 text-sm text-slate-500">Actualiza los datos de la desincorporación. La trazabilidad se conserva.</p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edit-tipo" value="Tipo de desincorporación *" />
                            <select id="edit-tipo" name="tipo" x-model="tipo" required class="form-control mt-1">
                                <option value="">Seleccione…</option>
                                @foreach ($tipos as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="edit-destinatario" value="Destinatario *" />
                            <input type="text" id="edit-destinatario" name="destinatario" x-model="destinatario" required maxlength="150" class="form-control mt-1">
                        </div>
                        <div>
                            <x-input-label for="edit-detalle" value="Detalle del destino" />
                            <input type="text" id="edit-detalle" name="detalle_destino" x-model="detalle_destino" maxlength="255" class="form-control mt-1">
                        </div>
                        <div>
                            <x-input-label for="edit-referencia" value="Referencia" />
                            <input type="text" id="edit-referencia" name="referencia" x-model="referencia" maxlength="100" class="form-control mt-1"
                                   placeholder="Factura, acta, ticket…">
                        </div>
                        <div>
                            <x-input-label for="edit-fecha" value="Fecha *" />
                            <input type="date" id="edit-fecha" name="fecha" x-model="fecha" required class="form-control mt-1">
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <x-input-label for="edit-monto" value="Monto" />
                                <input type="number" step="0.01" min="0" id="edit-monto" name="monto" x-model="monto"
                                       :required="exigeMonto()" class="form-control mt-1">
                            </div>
                            <div>
                                <x-input-label for="edit-moneda" value="Moneda" />
                                <input type="text" id="edit-moneda" name="moneda" x-model="moneda" maxlength="3" class="form-control mt-1">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="edit-motivo" value="Motivo *" />
                        <textarea id="edit-motivo" name="motivo" x-model="motivo" rows="3" required maxlength="500" class="form-control mt-1"></textarea>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost"
                                @click="$dispatch('close-modal', 'editar-desincorporacion')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" :disabled="id === ''">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </x-modal>
    @endif
</x-app-layout>
