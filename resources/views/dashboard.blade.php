<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Dashboard" subtitle="Resumen general del inventario de equipos" icon="home" />
    </x-slot>

    @php
        $total = $total ?? 0;
        $reparacion = ($porGrupo['Reparacion'] ?? 0) + ($porGrupo['Defectuoso'] ?? 0);

        $distribucion = [
            ['clave' => 'Disponible', 'label' => 'Disponibles', 'color' => 'bg-emerald-500'],
            ['clave' => 'Asignado', 'label' => 'Asignados', 'color' => 'bg-sky-500'],
            ['clave' => 'Reparacion', 'label' => 'En reparación', 'color' => 'bg-amber-500'],
            ['clave' => 'Defectuoso', 'label' => 'Defectuosos', 'color' => 'bg-orange-500'],
            ['clave' => 'Desincorporado', 'label' => 'Desincorporados', 'color' => 'bg-rose-500'],
        ];
        // El denominador son solo los grupos con representación visual: los
        // activos sin catálogo de estado agrupan bajo su estado denormalizado
        // y no deben diluir los porcentajes de la distribución.
        $sumaDistribucion = max(collect($distribucion)->sum(fn ($fila) => $porGrupo[$fila['clave']] ?? 0), 1);
    @endphp

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat-card label="Total equipos" :value="$total" icon="cube" color="brand" :href="route('activos.index')" />
        <x-stat-card label="Asignados" :value="$porGrupo['Asignado'] ?? 0" icon="users" color="sky" />
        <x-stat-card label="Disponibles" :value="$porGrupo['Disponible'] ?? 0" icon="check-circle" color="emerald" />
        <x-stat-card label="Reparación / defectuosos" :value="$reparacion" icon="wrench" color="amber" />
        <x-stat-card label="Desincorporados" :value="$porGrupo['Desincorporado'] ?? 0" icon="inbox" color="rose" :href="route('desincorporados.index')" />
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Distribución --}}
        <div class="card lg:col-span-1">
            <div class="card-header">
                <h3 class="card-title">Distribución por estado</h3>
                <x-icon name="chart" :size="18" class="text-slate-400" />
            </div>
            <div class="card-body space-y-4">
                @if ($total === 0)
                    <x-empty-state title="Sin datos" description="Registra equipos para ver la distribución." icon="chart" />
                @else
                    <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                        @foreach ($distribucion as $fila)
                            @php $valor = $porGrupo[$fila['clave']] ?? 0; @endphp
                            @if ($valor > 0)
                                <div class="{{ $fila['color'] }}" style="width: {{ round($valor / $sumaDistribucion * 100, 2) }}%" title="{{ $fila['label'] }}: {{ $valor }}"></div>
                            @endif
                        @endforeach
                    </div>

                    <ul class="space-y-2.5">
                        @foreach ($distribucion as $fila)
                            @php
                                $valor = $porGrupo[$fila['clave']] ?? 0;
                                $porcentaje = round($valor / $sumaDistribucion * 100);
                            @endphp
                            <li class="flex items-center gap-3 text-sm">
                                <span class="h-2.5 w-2.5 rounded-full {{ $fila['color'] }}"></span>
                                <span class="flex-1 text-slate-600">{{ $fila['label'] }}</span>
                                <span class="font-semibold text-slate-900">{{ $valor }}</span>
                                <span class="w-10 text-right text-xs text-slate-400">{{ $porcentaje }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Últimos equipos --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">Últimos equipos registrados</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('activos.index') }}" class="btn btn-ghost btn-sm">Ver todos</a>
                    @if (auth()->user()->puedeEscribir())
                        <a href="{{ route('activos.create') }}" class="btn btn-primary btn-sm">
                            <x-icon name="plus" :size="16" /> Registrar
                        </a>
                    @endif
                </div>
            </div>

            @empty($recientes->count())
                <x-empty-state title="Aún no hay equipos registrados"
                               description="Comienza registrando tu primer equipo en el inventario."
                               icon="cube">
                    <x-slot name="action">
                        @if (auth()->user()->puedeEscribir())
                            <a href="{{ route('activos.create') }}" class="btn btn-primary">
                                <x-icon name="plus" :size="18" /> Registrar equipo
                            </a>
                        @endif
                    </x-slot>
                </x-empty-state>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Etiqueta</th>
                                <th>Nombre</th>
                                <th class="hidden md:table-cell">Categoría</th>
                                <th class="hidden lg:table-cell">Asignado a</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recientes as $activo)
                                <tr>
                                    <td class="font-medium">
                                        <a class="link" href="{{ route('activos.show', $activo) }}">
                                            {{ $activo->Etiqueta_activo }}
                                        </a>
                                    </td>
                                    <td>{{ $activo->Nombre_de_activo }}</td>
                                    <td class="hidden text-slate-500 md:table-cell">{{ $activo->Categoria ?? '—' }}</td>
                                    <td class="hidden text-slate-500 lg:table-cell">
                                        {{ $activo->asignacionActiva?->personal?->NombreCompleto ?? '—' }}
                                    </td>
                                    <td>
                                        <x-status-badge :grupo="$activo->estadoCatalogo?->Grupo" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endempty
        </div>
    </div>
</x-app-layout>
