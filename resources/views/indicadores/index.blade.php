<x-app-layout ancho="max-w-screen-2xl">
    <x-slot name="header">
        <x-page-header title="Reportes y KPIs" subtitle="Indicadores del inventario y reportes estratégicos" icon="chart">
            <x-slot name="actions">
                <a href="{{ route('indicadores.exportar', array_merge($filtros, ['reporte' => \App\Services\IndicadoresService::KPI_RESUMEN])) }}"
                   class="btn btn-secondary">
                    <x-icon name="download" :size="18" /> CSV de KPIs
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $formatoCelda = function ($valor, string $tipo) {
            return match ($tipo) {
                'numero' => number_format((float) $valor, 0, ',', '.'),
                'dinero' => number_format((float) $valor, 2),
                'porcentaje' => number_format((float) $valor, 1).' %',
                'fecha' => filled($valor) ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—',
                default => filled($valor) ? $valor : '—',
            };
        };
        $alineacion = fn (string $tipo) => in_array($tipo, ['numero', 'dinero', 'porcentaje'], true) ? 'text-right tabular-nums' : '';
    @endphp

    {{-- Filtros: periodo (movimientos), categoría y grupo (todo el módulo). --}}
    <form method="GET" action="{{ route('indicadores.index') }}" class="card card-body">
        <input type="hidden" name="reporte" value="{{ $reporte }}">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] }}" class="form-control">
            </div>
            <div>
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control">
            </div>
            <div>
                <label for="categoria" class="form-label">Categoría</label>
                <select id="categoria" name="categoria" class="form-control">
                    <option value="">Todas</option>
                    @foreach ($categorias as $opcion)
                        <option value="{{ $opcion }}" @selected($filtros['categoria'] === $opcion)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="grupo" class="form-label">Grupo de estado</label>
                <select id="grupo" name="grupo" class="form-control">
                    <option value="">Todos</option>
                    @foreach ($grupos as $opcion)
                        <option value="{{ $opcion }}" @selected($filtros['grupo'] === $opcion)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-neutral flex-1">
                    <x-icon name="funnel" :size="18" /> Filtrar
                </button>
                <a href="{{ route('indicadores.index') }}" class="btn btn-ghost">Limpiar</a>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            El periodo filtra solo los movimientos (altas, bajas, mantenimientos y compras); los indicadores de stock muestran siempre el inventario actual.
        </p>
    </form>

    {{-- Tarjetas de KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <x-stat-card label="Equipos registrados" :value="number_format($kpis['equipos'])" icon="cube" color="brand" />
        <x-stat-card label="Equipos asignados" :value="number_format($kpis['asignados'])" icon="users" color="sky" />
        <x-stat-card label="Tasa de utilización" :value="number_format($kpis['utilizacion'], 1).' %'" icon="chart" color="emerald" />
        <x-stat-card label="Equipos disponibles" :value="number_format($kpis['disponibles'])" icon="check-circle" color="emerald" />
        <x-stat-card label="En reparación o defectuosos" :value="number_format($kpis['reparacion'])" icon="wrench" color="amber" />
        <x-stat-card :label="'Garantías por vencer ('.config('zaico.alertas.garantia_dias', 30).' días)'" :value="number_format($kpis['garantias_por_vencer'])" icon="warning" color="amber" />
        <x-stat-card label="Garantías vencidas" :value="number_format($kpis['garantias_vencidas'])" icon="warning" color="rose" />
        <x-stat-card label="Costo total del inventario" :value="number_format($kpis['costo_total'], 2)" icon="currency" color="slate" />
        <x-stat-card label="Altas en el periodo" :value="number_format($kpis['altas'])" icon="plus" color="sky" />
        <x-stat-card label="Bajas en el periodo" :value="number_format($kpis['bajas'])" icon="archive" color="rose" />
        <x-stat-card label="Mantenimientos en el periodo" :value="number_format($kpis['mantenimientos'])" icon="clipboard" color="brand" />
    </div>

    {{-- Gráfico principal: movimientos mensuales --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Movimientos mensuales</h3>
        </div>
        <div class="card-body">
            <x-charts.linea
                :etiquetas="$graficos['serie']['etiquetas']"
                :series="[
                    ['nombre' => 'Altas', 'color' => '#10b981', 'valores' => $graficos['serie']['altas']],
                    ['nombre' => 'Bajas', 'color' => '#f43f5e', 'valores' => $graficos['serie']['bajas']],
                    ['nombre' => 'Mantenimientos', 'color' => '#0ea5e9', 'valores' => $graficos['serie']['mantenimientos']],
                ]"
                titulo="Altas, bajas y mantenimientos por mes" />
        </div>
    </div>

    {{-- Gráficos secundarios --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Compras por mes (monto)</h3>
            </div>
            <div class="card-body">
                <x-charts.barras
                    :datos="collect($graficos['serie']['etiquetas'])->map(fn ($e, $i) => ['etiqueta' => $e, 'valor' => $graficos['serie']['compras'][$i]])->all()"
                    color="#8b5cf6"
                    titulo="Monto de compras por mes" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Costo por categoría</h3>
            </div>
            <div class="card-body">
                <x-charts.barras :datos="$graficos['costos_categoria']" color="#f59e0b" titulo="Costo por categoría" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Distribución por estado</h3>
            </div>
            <div class="card-body">
                <x-charts.donut :datos="$kpis['distribucion']" titulo="Distribución del inventario por estado" total_etiqueta="Equipos" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Equipos por categoría</h3>
            </div>
            <div class="card-body">
                <x-charts.barras :datos="$graficos['categorias']" color="#0ea5e9" titulo="Equipos por categoría" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Equipos por ubicación</h3>
            </div>
            <div class="card-body">
                <x-charts.barras :datos="$graficos['ubicaciones']" :horizontal="true" color="#10b981" titulo="Equipos por ubicación" />
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Mantenimientos por tipo</h3>
            </div>
            <div class="card-body">
                <x-charts.donut :datos="$graficos['mantenimientos_tipo']" titulo="Mantenimientos por tipo" total_etiqueta="Intervenciones" />
            </div>
        </div>
    </div>

    {{-- Reportes estratégicos --}}
    <div class="card">
        <div class="card-header flex-wrap">
            <h3 class="card-title">Reportes estratégicos</h3>
            <a href="{{ route('indicadores.exportar', array_merge($filtros, ['reporte' => $tabla['clave']])) }}"
               class="btn btn-secondary">
                <x-icon name="download" :size="18" /> Exportar PDF
            </a>
        </div>

        <div class="border-b border-slate-200 px-4 py-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($reportes as $clave => $nombre)
                    <a href="{{ route('indicadores.index', array_merge($filtros, ['reporte' => $clave])) }}"
                       class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $reporte === $clave ? 'bg-brand-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $nombre }}
                    </a>
                @endforeach
            </div>
        </div>

        @if (empty($tabla['filas']))
            <x-empty-state title="Sin datos para este reporte"
                           description="Ajusta el periodo o los filtros y vuelve a intentarlo." icon="chart" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($tabla['columnas'] as $columna)
                                <th class="{{ $alineacion($columna['tipo']) }}">{{ $columna['etiqueta'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tabla['filas'] as $fila)
                            <tr>
                                @foreach ($tabla['columnas'] as $columna)
                                    <td class="{{ $alineacion($columna['tipo']) }}">{{ $formatoCelda($fila[$columna['clave']] ?? null, $columna['tipo']) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (! empty($tabla['totales']))
                        <tfoot>
                            <tr class="bg-slate-50 font-semibold">
                                @foreach ($tabla['columnas'] as $columna)
                                    <td class="{{ $alineacion($columna['tipo']) }}">
                                        {{ $formatoCelda($tabla['totales'][$columna['clave']] ?? null, $columna['tipo']) }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif

        @if ($tabla['nota'])
            <div class="border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                {{ $tabla['nota'] }}
            </div>
        @endif
    </div>
</x-app-layout>
