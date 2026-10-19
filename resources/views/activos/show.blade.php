@php
    $puedeVerAuditoria = auth()->user()->isAdmin() || auth()->user()->role === 'Auditor';
    $compra = $activo->compra;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$activo->Nombre_de_activo"
                       :subtitle="'Etiqueta: ' . $activo->Etiqueta_activo . ($activo->Categoria ? ' · ' . $activo->Categoria : '')"
                       icon="cube">
            <x-slot name="actions">
                <x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" />
                <a href="{{ route('activos.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('activos.edit', $activo) }}" class="btn btn-primary">
                        <x-icon name="pencil" :size="18" /> Editar
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div x-data="{
            tab: 'datos',
            eventos: null,
            cargandoEventos: false,
            errorEventos: false,
            cargarEventos() {
                if (this.eventos !== null || this.cargandoEventos) { return; }
                this.cargandoEventos = true;
                this.errorEventos = false;
                fetch('{{ route('activos.eventos', $activo) }}', { headers: { 'Accept': 'application/json' } })
                    .then((r) => { if (!r.ok) { throw new Error(); } return r.json(); })
                    .then((data) => { this.eventos = data; })
                    .catch(() => { this.errorEventos = true; })
                    .finally(() => { this.cargandoEventos = false; });
            }
         }"
         class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        {{-- Columna principal con pestañas --}}
        <div class="card lg:col-span-2">
            <div class="border-b border-slate-200 px-3 pt-3">
                <nav class="flex flex-wrap gap-1" role="tablist">
                    @php
                        $tabs = [
                            ['id' => 'datos', 'label' => 'Datos', 'icon' => 'document'],
                            ['id' => 'compra', 'label' => 'Compra', 'icon' => 'currency'],
                            ['id' => 'asignaciones', 'label' => 'Asignaciones', 'icon' => 'users'],
                            ['id' => 'mantenimientos', 'label' => 'Mantenimientos', 'icon' => 'wrench'],
                        ];

                        $esServidor = $activo->esServidor() || $activo->tarjetasRed->isNotEmpty();

                        if ($esServidor) {
                            $tabs[] = ['id' => 'servidor', 'label' => 'Servidor', 'icon' => 'cpu-chip'];
                        }
                    @endphp
                    @foreach ($tabs as $t)
                        <button type="button" role="tab" @click="tab = '{{ $t['id'] }}'"
                                :class="tab === '{{ $t['id'] }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition">
                            <x-icon :name="$t['icon']" :size="18" /> {{ $t['label'] }}
                        </button>
                    @endforeach

                    @if ($puedeVerAuditoria)
                        <button type="button" role="tab" @click="tab = 'auditoria'; cargarEventos()"
                                :class="tab === 'auditoria' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition">
                            <x-icon name="shield" :size="18" /> Historial de movimientos
                        </button>
                    @endif
                </nav>
            </div>

            {{-- Datos --}}
            <div x-show="tab === 'datos'" x-cloak class="card-body">
                @if ($activo->Imagen_Activo)
                    <img src="{{ route('activos.imagen', $activo) }}" alt="{{ $activo->Nombre_de_activo }}"
                         class="mb-5 max-h-64 w-auto rounded-lg border border-slate-200 object-contain">
                @endif
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Etiqueta</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->Etiqueta_activo }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Serial</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->Serial ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Categoría</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->Categoria ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fabricante / Modelo</dt>
                        <dd class="font-medium text-slate-900">
                            {{ trim(($activo->Fabricante ?? '—').' / '.($activo->Modelo ?? '—')) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Ubicación</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->Ubicacion ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Ubicación predeterminada</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->Ubicacion_Predeterminada ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Dirección MAC</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->Direccion_MAC ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Estado de catálogo</dt>
                        <dd class="font-medium text-slate-900">{{ $activo->estadoCatalogo?->Nombre ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Observaciones</dt>
                        <dd class="whitespace-pre-line font-medium text-slate-900">{{ $activo->Observaciones ?? '—' }}</dd>
                    </div>
                    <div class="border-t border-slate-200 pt-4 text-xs text-slate-400 sm:col-span-2">
                        Registrado el {{ $activo->Creado_el?->format('d/m/Y H:i') }}
                    </div>
                </dl>
            </div>

            {{-- Compra --}}
            <div x-show="tab === 'compra'" x-cloak class="card-body">
                @if ($compra)
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">N.º de requisición / orden de compra</dt>
                            <dd class="font-medium text-slate-900">{{ $compra->Numero_Requisicion ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">N.º de factura</dt>
                            <dd class="font-medium text-slate-900">{{ $compra->Numero_factura ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Proveedor</dt>
                            <dd class="font-medium text-slate-900">{{ $compra->Proveedor ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Fecha de compra</dt>
                            <dd class="font-medium text-slate-900">{{ $compra->Fecha_Compra?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Costo</dt>
                            <dd class="font-medium text-slate-900">
                                {{ $compra->Costo_compra !== null ? number_format((float) $compra->Costo_compra, 2) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Tiempo de garantía</dt>
                            <dd class="font-medium text-slate-900">{{ $compra->Tiempo_garantia ?? '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Garantía (detalle)</dt>
                            <dd class="font-medium text-slate-900">{{ $compra->Garantia ?? '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Sin datos de compra registrados.</p>
                @endif

                @if ($compra?->Imagen_Factura)
                    <div class="mt-4">
                        <p class="text-sm text-slate-500">Fotografía de la factura</p>
                        <a href="{{ route('activos.factura', $activo) }}" target="_blank" rel="noopener">
                            <img src="{{ route('activos.factura', $activo) }}" alt="Factura de compra"
                                 class="mt-2 h-48 w-auto rounded-md border border-slate-200 object-contain">
                        </a>
                    </div>
                @endif

                @if (auth()->user()->puedeEscribir())
                    <form method="POST" action="{{ route('activos.compra.update', $activo) }}"
                          enctype="multipart/form-data"
                          class="mt-5 border-t border-slate-200 pt-5">
                        @csrf
                        @method('PUT')
                        <h4 class="mb-3 text-sm font-semibold text-slate-800">
                            {{ $compra ? 'Editar datos de compra' : 'Registrar datos de compra' }}
                        </h4>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label for="Numero_Requisicion" value="N.º de requisición / orden de compra" />
                                <x-text-input id="Numero_Requisicion" name="Numero_Requisicion" type="text" class="mt-1 w-full"
                                              :value="old('Numero_Requisicion', $compra?->Numero_Requisicion)" />
                                <x-input-error :messages="$errors->get('Numero_Requisicion')" />
                            </div>
                            <div>
                                <x-input-label for="Numero_factura" value="N.º de factura" />
                                <x-text-input id="Numero_factura" name="Numero_factura" type="text" class="mt-1 w-full"
                                              :value="old('Numero_factura', $compra?->Numero_factura)" />
                                <x-input-error :messages="$errors->get('Numero_factura')" />
                            </div>
                            <div>
                                <x-input-label for="Proveedor" value="Proveedor" />
                                <x-text-input id="Proveedor" name="Proveedor" type="text" class="mt-1 w-full"
                                              :value="old('Proveedor', $compra?->Proveedor)" />
                                <x-input-error :messages="$errors->get('Proveedor')" />
                            </div>
                            <div>
                                <x-input-label for="Fecha_Compra" value="Fecha de compra" />
                                <input type="date" id="Fecha_Compra" name="Fecha_Compra" class="form-control mt-1"
                                       value="{{ old('Fecha_Compra', $compra?->Fecha_Compra?->format('Y-m-d')) }}">
                                <x-input-error :messages="$errors->get('Fecha_Compra')" />
                            </div>
                            <div>
                                <x-input-label for="Costo_compra" value="Costo" />
                                <x-text-input id="Costo_compra" name="Costo_compra" type="number" step="0.01" min="0" class="mt-1 w-full"
                                              :value="old('Costo_compra', $compra?->Costo_compra)" />
                                <x-input-error :messages="$errors->get('Costo_compra')" />
                            </div>
                            <div>
                                <x-input-label for="Tiempo_garantia" value="Tiempo de garantía" />
                                <x-text-input id="Tiempo_garantia" name="Tiempo_garantia" type="text" class="mt-1 w-full"
                                              placeholder="Ej.: 12 meses"
                                              :value="old('Tiempo_garantia', $compra?->Tiempo_garantia)" />
                                <x-input-error :messages="$errors->get('Tiempo_garantia')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="Garantia" value="Garantía (detalle)" />
                                <x-text-input id="Garantia" name="Garantia" type="text" class="mt-1 w-full"
                                              :value="old('Garantia', $compra?->Garantia)" />
                                <x-input-error :messages="$errors->get('Garantia')" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="imagen_factura" value="Foto de la factura (JPG, PNG o WEBP · máx. 10 MB)" />
                                <input type="file" id="imagen_factura" name="imagen_factura" accept=".jpg,.jpeg,.png,.webp"
                                       class="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                                <x-input-error :messages="$errors->get('imagen_factura')" />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-4 w-full">
                            <x-icon name="check-circle" :size="18" /> Guardar datos de compra
                        </button>
                    </form>
                @endif
            </div>

            {{-- Asignaciones --}}
            <div x-show="tab === 'asignaciones'" x-cloak>
                @if ($historial->isEmpty())
                    <x-empty-state title="Sin asignaciones" description="Este equipo nunca ha sido asignado." icon="users" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Persona</th>
                                    <th>Asignado</th>
                                    <th>Devuelto</th>
                                    <th>Estado</th>
                                    <th class="hidden md:table-cell">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($historial as $asignacion)
                                    <tr>
                                        <td class="font-medium text-slate-900">{{ $asignacion->personal?->NombreCompleto ?? '—' }}</td>
                                        <td>{{ $asignacion->Fecha_asignacion?->format('d/m/Y') }}</td>
                                        <td>{{ $asignacion->Fecha_devolucion?->format('d/m/Y') ?? '—' }}</td>
                                        <td>
                                            @if ($asignacion->Estado === 'Activa')
                                                <x-badge color="sky" :dot="true">Activa</x-badge>
                                            @else
                                                <x-badge color="slate" :dot="true">{{ $asignacion->Estado }}</x-badge>
                                            @endif
                                        </td>
                                        <td class="hidden text-slate-500 md:table-cell">{{ $asignacion->Observaciones ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($historial->hasPages())
                        <div class="border-t border-slate-200 px-4 py-3">
                            {{ $historial->links() }}
                        </div>
                    @endif
                @endif
            </div>

            {{-- Mantenimientos --}}
            <div x-show="tab === 'mantenimientos'" x-cloak class="card-body">
                @if ($activo->mantenimientos->isEmpty())
                    <p class="text-sm text-slate-500">Sin mantenimientos registrados.</p>
                @else
                    <ul class="mb-4 divide-y divide-slate-100">
                        @foreach ($activo->mantenimientos->sortByDesc('Fecha_mantenimiento')->take(5) as $mantenimiento)
                            <li class="py-3">
                                <div class="flex items-center gap-2">
                                    <x-badge :color="$mantenimiento->Tipo === 'Preventivo' ? 'sky' : 'amber'">{{ $mantenimiento->Tipo }}</x-badge>
                                    <span class="text-xs text-slate-500">{{ $mantenimiento->Fecha_mantenimiento?->format('d/m/Y') }}</span>
                                </div>
                                <p class="mt-1.5 text-sm text-slate-700 whitespace-pre-line">{{ $mantenimiento->Descripcion }}</p>
                                @if ($mantenimiento->evidencias->isNotEmpty())
                                    <ul class="mt-2 flex flex-wrap gap-2">
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
                    <a href="{{ route('activos.mantenimientos', $activo) }}" class="link">Ver todos los mantenimientos →</a>
                @endif

                @if (auth()->user()->puedeEscribir())
                    <form method="POST" action="{{ route('activos.mantenimientos.store', $activo) }}"
                          enctype="multipart/form-data" class="mt-5 border-t border-slate-200 pt-5">
                        @csrf
                        <h4 class="mb-3 text-sm font-semibold text-slate-800">Registrar mantenimiento</h4>

                        <div class="mb-3 grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="Tipo" value="Tipo *" />
                                <select id="Tipo" name="Tipo" required class="form-control mt-1">
                                    <option value="Preventivo">Preventivo</option>
                                    <option value="Correctivo">Correctivo</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="Fecha_mantenimiento" value="Fecha *" />
                                <input type="date" id="Fecha_mantenimiento" name="Fecha_mantenimiento" required
                                       value="{{ old('Fecha_mantenimiento', now()->toDateString()) }}" class="form-control mt-1">
                            </div>
                        </div>

                        <x-input-label for="Descripcion" value="Descripción *" />
                        <textarea id="Descripcion" name="Descripcion" rows="2" required class="form-control mt-1"
                                  placeholder="Trabajo realizado, repuestos, hallazgos…">{{ old('Descripcion') }}</textarea>

                        <x-input-label for="evidencias" value="Evidencias (JPG, PNG, PDF, MP4 · máx. 20 MB c/u)" class="mt-3" />
                        <input type="file" id="evidencias" name="evidencias[]" multiple
                               accept=".jpg,.jpeg,.png,.pdf,.mp4"
                               class="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200">

                        <button type="submit" class="btn btn-primary mt-4 w-full">
                            <x-icon name="check-circle" :size="18" /> Guardar mantenimiento
                        </button>
                    </form>
                @endif
            </div>

            {{-- Servidor --}}
            @if ($esServidor)
                <div x-show="tab === 'servidor'" x-cloak class="card-body">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">IP</dt>
                            <dd class="font-medium text-slate-900">{{ $activo->IP ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Estatus</dt>
                            <dd class="font-medium text-slate-900">
                                @php
                                    $colorEstatus = match ($activo->Estatus_Servidor) {
                                        'Encendido' => 'emerald',
                                        'Mantenimiento' => 'amber',
                                        default => 'slate',
                                    };
                                @endphp
                                @if ($activo->Estatus_Servidor)
                                    <x-badge :color="$colorEstatus" :dot="true">
                                        {{ \App\Enums\EstatusServidor::etiquetaDe($activo->Estatus_Servidor) }}
                                    </x-badge>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Sistema operativo</dt>
                            <dd class="font-medium text-slate-900">{{ $activo->Sistema_Operativo ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Memoria RAM</dt>
                            <dd class="font-medium text-slate-900">{{ $activo->Memoria_RAM ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Capacidad de disco</dt>
                            <dd class="font-medium text-slate-900">{{ $activo->Capacidad_Disco ?? '—' }}</dd>
                        </div>
                    </dl>

                    <h4 class="mb-2 mt-6 text-sm font-semibold text-slate-800">Tarjetas de red</h4>

                    @if ($activo->tarjetasRed->isEmpty())
                        <p class="text-sm text-slate-500">Sin tarjetas de red registradas.</p>
                    @else
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>N.º</th>
                                        <th>Interfaz</th>
                                        <th>Dirección MAC</th>
                                        <th>IP</th>
                                        <th>Switch</th>
                                        <th>Puerto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activo->tarjetasRed as $tarjeta)
                                        <tr>
                                            <td class="font-medium text-slate-900">{{ $tarjeta->Numero }}</td>
                                            <td>{{ $tarjeta->Nombre ?? '—' }}</td>
                                            <td>{{ $tarjeta->Direccion_MAC ?? '—' }}</td>
                                            <td>{{ $tarjeta->IP ?? '—' }}</td>
                                            <td>{{ $tarjeta->Switch ?? '—' }}</td>
                                            <td>{{ $tarjeta->Puerto ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Auditoría (carga diferida) --}}
            @if ($puedeVerAuditoria)
                <div x-show="tab === 'auditoria'" x-cloak class="card-body">
                    <template x-if="cargandoEventos">
                        <p class="py-8 text-center text-sm text-slate-500">Cargando eventos…</p>
                    </template>
                    <template x-if="errorEventos">
                        <p class="py-8 text-center text-sm text-rose-600">No se pudieron cargar los eventos.</p>
                    </template>
                    <template x-if="eventos && eventos.length === 0">
                        <x-empty-state title="Sin eventos" description="No hay registros de auditoría para este equipo." icon="shield" />
                    </template>
                    <template x-if="eventos && eventos.length > 0">
                        <ul class="divide-y divide-slate-100">
                            <template x-for="evento in eventos" :key="evento.id">
                                <li class="py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-800" x-text="evento.Tipo_evento"></span>
                                        <span class="text-xs text-slate-400" x-text="evento.Fecha"></span>
                                        <span class="text-xs text-slate-500" x-text="evento.usuario ? 'por ' + evento.usuario.name : ''"></span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-600" x-text="evento.Descripcion"></p>
                                    <template x-if="evento.Valor_anterior || evento.Valor_nuevo">
                                        <p class="mt-1 text-xs text-slate-500">
                                            <span x-text="evento.Valor_anterior || '—'"></span>
                                            <span class="mx-1">→</span>
                                            <span class="font-medium text-slate-700" x-text="evento.Valor_nuevo || '—'"></span>
                                        </p>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
            @endif
        </div>

        {{-- Columna lateral de acciones --}}
        <div class="space-y-5">
            @if (auth()->user()->puedeEscribir())
                @if ($activo->asignacionActiva)
                    <div class="card card-body">
                        <h3 class="mb-1 text-base font-semibold text-slate-800">Devolver equipo</h3>
                        <p class="mb-4 text-sm text-slate-500">
                            Asignado a:
                            <span class="font-medium text-slate-800">{{ $activo->asignacionActiva->personal?->NombreCompleto }}</span>
                        </p>

                        <form method="POST" action="{{ route('activos.devolver', $activo) }}">
                            @csrf
                            <x-input-label for="Fecha_devolucion" value="Fecha de devolución *" />
                            <input type="date" id="Fecha_devolucion" name="Fecha_devolucion" required
                                   value="{{ old('Fecha_devolucion', now()->toDateString()) }}" class="form-control mt-1">

                            <x-input-label for="Observaciones_dev" value="Observaciones" class="mt-3" />
                            <textarea id="Observaciones_dev" name="Observaciones" rows="2" class="form-control mt-1"></textarea>

                            <button type="submit" class="btn btn-warning mt-4 w-full">
                                <x-icon name="refresh" :size="18" /> Registrar devolución
                            </button>
                        </form>
                    </div>
                @elseif ($activo->estadoCatalogo?->Grupo !== 'Desincorporado')
                    <div class="card card-body">
                        <h3 class="mb-4 text-base font-semibold text-slate-800">Asignar equipo</h3>

                        @if ($personas->isEmpty())
                            <p class="text-sm text-slate-500">
                                No hay personas activas registradas.
                                <a href="{{ route('personal.create') }}" class="link">Registrar persona</a>
                            </p>
                        @else
                            <form method="POST" action="{{ route('activos.asignar', $activo) }}">
                                @csrf
                                <x-input-label for="fk_Personal" value="Asignar a *" />
                                <x-search-select
                                    name="fk_Personal"
                                    class="mt-1"
                                    placeholder="Escriba el nombre…"
                                    :required="true"
                                    :options="$personas->map(fn ($persona) => [
                                        'value' => $persona->id,
                                        'label' => $persona->NombreCompleto,
                                        'meta' => $persona->Cargo ?? $persona->Nombre_usuario,
                                    ])->values()->all()" />
                                <x-input-error :messages="$errors->get('fk_Personal')" />

                                <x-input-label for="Fecha_asignacion" value="Fecha de asignación *" class="mt-3" />
                                <input type="date" id="Fecha_asignacion" name="Fecha_asignacion" required
                                       value="{{ old('Fecha_asignacion', now()->toDateString()) }}" class="form-control mt-1">

                                <x-input-label for="Observaciones_asig" value="Observaciones" class="mt-3" />
                                <textarea id="Observaciones_asig" name="Observaciones" rows="2" class="form-control mt-1"></textarea>

                                <button type="submit" class="btn btn-primary mt-4 w-full">
                                    <x-icon name="check-circle" :size="18" /> Asignar
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="card card-body">
                    <h3 class="mb-4 text-base font-semibold text-slate-800">Cambiar estado</h3>
                    <form method="POST" action="{{ route('activos.estado', $activo) }}">
                        @csrf
                        @method('PUT')

                        <x-input-label for="fk_estado" value="Nuevo estado *" />
                        <x-search-select
                            name="fk_estado"
                            class="mt-1"
                            placeholder="Escriba para buscar…"
                            :required="true"
                            :selected="$activo->fk_estado"
                            :options="$estados->map(fn ($estado) => [
                                'value' => $estado->id,
                                'label' => $estado->Nombre,
                                'meta' => $estado->Grupo,
                            ])->values()->all()" />
                        <x-input-error :messages="$errors->get('fk_estado')" />

                        <x-input-label for="motivo" value="Motivo *" class="mt-3" />
                        <textarea id="motivo" name="motivo" rows="2" required class="form-control mt-1"
                                  placeholder="Justificación del cambio de estado">{{ old('motivo') }}</textarea>

                        <button type="submit" class="btn btn-neutral mt-4 w-full">
                            <x-icon name="refresh" :size="18" /> Aplicar cambio
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
