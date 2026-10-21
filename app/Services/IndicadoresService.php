<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Compra;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\Mantenimiento;
use App\Services\Concerns\VencimientoGarantia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Indicadores y reportes estratégicos del módulo «Reportes y KPIs».
 *
 * Reglas del módulo (AGENTS.md § Reportes y KPIs):
 *
 * - Todo filtro va en SQL y todas las consultas son agregadas portables
 *   entre PostgreSQL (producción) y SQLite (tests): mismo `COALESCE` de
 *   grupo que `DashboardService` y expresiones de fecha condicionadas al
 *   driver cuando hace falta (`mesSql`, `aniosSql`).
 * - Semántica de los filtros: `categoria` y `grupo` restringen el inventario
 *   en todo el módulo (stock y movimientos); `desde`/`hasta` acotan solo los
 *   movimientos del periodo (altas, bajas, mantenimientos, compras). El stock
 *   es una fotografía actual, no se reconstruye en el pasado.
 * - Sin caché: los agregados son baratos (tablas pequeñas) y deben
 *   reflejar el estado real en cada visita.
 */
class IndicadoresService
{
    use VencimientoGarantia;

    /**
     * Reportes estratégicos disponibles, en el orden en que se muestran.
     * Es la lista blanca que validan los Form Requests.
     *
     * @var array<string, string>
     */
    public const REPORTES = [
        'por-categoria' => 'Resumen por categoría',
        'por-ubicacion' => 'Resumen por ubicación',
        'antiguedad' => 'Antigüedad del inventario',
        'garantias' => 'Estado de garantías',
        'mantenimiento' => 'Equipos más intervenidos',
        'rotacion' => 'Rotación mensual',
        'costos' => 'Costos del inventario',
    ];

    /** Clave del resumen de KPIs (se exporta en CSV). */
    public const KPI_RESUMEN = 'kpi-resumen';

    /** Grupo de estado resuelto: catálogo vinculado o estado denormalizado. */
    private const GRUPO_SQL = 'COALESCE("ESTADOS_ACTIVO"."Grupo", "ACTIVOS"."Estado")';

    /** Colores compartidos entre la pantalla y el PDF. */
    private const COLORES_GARANTIA = [
        'Vencida' => '#f43f5e',
        'Por vencer' => '#f59e0b',
        'Vigente' => '#10b981',
        'Sin datos' => '#94a3b8',
    ];

    private const ORDEN_GARANTIA = ['Vencida' => 0, 'Por vencer' => 1, 'Vigente' => 2, 'Sin datos' => 3];

    public function __construct(private readonly ReporteService $reportes) {}

    /**
     * Filtros por defecto: últimos 12 meses completos. Si el llamador solo
     * envía uno de los extremos, el otro se deriva de forma coherente.
     *
     * @param  array{desde?: string, hasta?: string, categoria?: string|null, grupo?: string|null}  $entrada
     * @return array{desde: string, hasta: string, categoria: ?string, grupo: ?string}
     */
    public function normalizarFiltros(array $entrada): array
    {
        $desde = ! empty($entrada['desde']) ? Carbon::parse($entrada['desde']) : null;
        $hasta = ! empty($entrada['hasta']) ? Carbon::parse($entrada['hasta']) : null;

        if ($hasta === null) {
            // Solo «desde» en el futuro: el periodo es ese día, no hasta hoy.
            $hasta = ($desde !== null && $desde->greaterThan(now())) ? $desde->copy() : ($desde?->copy() ?? now());
        }

        if ($desde === null) {
            $desde = $hasta->copy()->subMonths(11)->startOfMonth();
        }

        $categoria = $entrada['categoria'] ?? null;
        $grupo = $entrada['grupo'] ?? null;

        return [
            'desde' => $desde->startOfDay()->toDateString(),
            'hasta' => $hasta->startOfDay()->toDateString(),
            'categoria' => ($categoria !== null && trim($categoria) !== '') ? trim($categoria) : null,
            'grupo' => ($grupo !== null && trim($grupo) !== '') ? trim($grupo) : null,
        ];
    }

    /**
     * Tarjetas de KPIs del módulo (stock actual + movimientos del periodo).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array{
     *     equipos: int, asignados: int, utilizacion: float, disponibles: int,
     *     reparacion: int, garantias_por_vencer: int, garantias_vencidas: int,
     *     costo_total: float, altas: int, bajas: int, mantenimientos: int,
     *     distribucion: list<array{clave: string, label: string, valor: int, color: string}>
     * }
     */
    public function kpis(array $filtros): array
    {
        $distribucion = $this->distribucionPorEstado($filtros);

        $totales = [];
        foreach ($distribucion as $fila) {
            $totales[$fila['clave']] = $fila['valor'];
        }

        $equipos = array_sum($totales);
        $desincorporados = $totales['Desincorporado'] ?? 0;

        // Denominador sin desincorporados (mínimo 1 para no dividir entre 0).
        $activos = max($equipos - $desincorporados, 1);

        $asignados = $this->baseActivos($filtros)
            ->whereHas('asignaciones', fn ($query) => $query->where('Estado', 'Activa'))
            ->count();

        [$porVencer, $vencidas] = $this->contarGarantias($filtros);

        $costo = (float) ($this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros)
            ->sum('COMPRAS.Costo_compra') ?? 0);

        $rango = $this->rango($filtros);

        $altas = $this->baseActivos($filtros)
            ->where('ACTIVOS.Creado_el', '>=', $rango['desde'])
            ->where('ACTIVOS.Creado_el', '<', $rango['hasta_exclusivo'])
            ->count();

        $bajas = $this->conActivo(Desincorporacion::query(), 'DESINCORPORACIONES.fk_activo', $filtros)
            ->where('DESINCORPORACIONES.Estado', 'Vigente')
            ->whereBetween('DESINCORPORACIONES.Fecha', [$rango['desde'], $rango['hasta']])
            ->count();

        $mantenimientos = $this->conActivo(Mantenimiento::query(), 'MANTENIMIENTOS.fk_activo', $filtros)
            ->whereBetween('MANTENIMIENTOS.Fecha_mantenimiento', [$rango['desde'], $rango['hasta']])
            ->count();

        return [
            'equipos' => (int) $equipos,
            'asignados' => (int) $asignados,
            'utilizacion' => round($asignados / $activos * 100, 1),
            'disponibles' => $totales['Disponible'] ?? 0,
            'reparacion' => ($totales['Reparacion'] ?? 0) + ($totales['Defectuoso'] ?? 0),
            'garantias_por_vencer' => $porVencer,
            'garantias_vencidas' => $vencidas,
            'costo_total' => $costo,
            'altas' => (int) $altas,
            'bajas' => (int) $bajas,
            'mantenimientos' => (int) $mantenimientos,
            'distribucion' => $distribucion,
        ];
    }

    /**
     * Conjunto de datos de los gráficos de la pantalla.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array{
     *     serie: array<string, mixed>,
     *     categorias: list<array{etiqueta: string, valor: int|float}>,
     *     ubicaciones: list<array{etiqueta: string, valor: int|float}>,
     *     costos_categoria: list<array{etiqueta: string, valor: int|float}>,
     *     mantenimientos_tipo: list<array{etiqueta: string, valor: int|float}>
     * }
     */
    public function graficos(array $filtros): array
    {
        $categorias = $this->baseActivos($filtros)
            ->selectRaw($this->categoriaSql().' as etiqueta, count(*) as valor')
            ->groupByRaw($this->categoriaSql())
            ->orderByDesc('valor')
            ->pluck('valor', 'etiqueta');

        $ubicaciones = $this->conActivo(Asignacion::query(), 'ASIGNACIONES.fk_Activo', $filtros)
            ->where('ASIGNACIONES.Estado', 'Activa')
            ->selectRaw($this->ubicacionSql().' as etiqueta, count(distinct '.$this->columnaSql('ACTIVOS.id').') as valor')
            ->groupByRaw($this->ubicacionSql())
            ->orderByDesc('valor')
            ->pluck('valor', 'etiqueta');

        $costos = $this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros)
            ->selectRaw($this->categoriaSql().' as etiqueta, SUM('.$this->columnaSql('COMPRAS.Costo_compra').') as valor')
            ->groupByRaw($this->categoriaSql())
            ->orderByDesc('valor')
            ->pluck('valor', 'etiqueta');

        $rango = $this->rango($filtros);

        $tipos = $this->conActivo(Mantenimiento::query(), 'MANTENIMIENTOS.fk_activo', $filtros)
            ->whereBetween('MANTENIMIENTOS.Fecha_mantenimiento', [$rango['desde'], $rango['hasta']])
            ->selectRaw("COALESCE({$this->columnaSql('MANTENIMIENTOS.Tipo')}, 'Sin tipo') as etiqueta, count(*) as valor")
            ->groupByRaw("COALESCE({$this->columnaSql('MANTENIMIENTOS.Tipo')}, 'Sin tipo')")
            ->orderByDesc('valor')
            ->pluck('valor', 'etiqueta');

        return [
            'serie' => $this->serieMensual($filtros),
            'categorias' => $this->conResto($categorias, 8, 'Otros'),
            'ubicaciones' => $this->conResto($ubicaciones, 10, 'Otras ubicaciones'),
            'costos_categoria' => $this->conResto($costos, 8, 'Otros'),
            'mantenimientos_tipo' => $this->conResto($tipos, 6, 'Otros'),
        ];
    }

    /**
     * Serie mensual de altas, bajas, mantenimientos y monto de compras
     * dentro del rango de fechas de los filtros.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array{claves: list<string>, etiquetas: list<string>, altas: list<int>, bajas: list<int>, mantenimientos: list<int>, compras: list<float>}
     */
    public function serieMensual(array $filtros): array
    {
        $claves = $this->meses($filtros);

        $altas = $this->agregarPorMes(
            $this->baseActivos($filtros),
            'ACTIVOS.Creado_el',
            $filtros,
            'count(*)',
            true,
        );

        $bajas = $this->agregarPorMes(
            $this->conActivo(Desincorporacion::query(), 'DESINCORPORACIONES.fk_activo', $filtros)
                ->where('DESINCORPORACIONES.Estado', 'Vigente'),
            'DESINCORPORACIONES.Fecha',
            $filtros,
            'count(*)',
        );

        $mantenimientos = $this->agregarPorMes(
            $this->conActivo(Mantenimiento::query(), 'MANTENIMIENTOS.fk_activo', $filtros),
            'MANTENIMIENTOS.Fecha_mantenimiento',
            $filtros,
            'count(*)',
        );

        $compras = $this->agregarPorMes(
            $this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros),
            'COMPRAS.Fecha_Compra',
            $filtros,
            'SUM('.$this->columnaSql('COMPRAS.Costo_compra').')',
        );

        $serie = [
            'claves' => $claves,
            'etiquetas' => array_map($this->etiquetaMes(...), $claves),
            'altas' => [],
            'bajas' => [],
            'mantenimientos' => [],
            'compras' => [],
        ];

        foreach ($claves as $clave) {
            $serie['altas'][] = (int) ($altas[$clave] ?? 0);
            $serie['bajas'][] = (int) ($bajas[$clave] ?? 0);
            $serie['mantenimientos'][] = (int) ($mantenimientos[$clave] ?? 0);
            $serie['compras'][] = round((float) ($compras[$clave] ?? 0), 2);
        }

        return $serie;
    }

    /**
     * Construye un reporte estratégico: columnas, filas, totales y el
     * gráfico SVG embebible en el PDF. Los importes se calculan con las
     * mismas reglas que la pantalla.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array{
     *     clave: string, titulo: string, columnas: list<array{clave: string, etiqueta: string, tipo: string}>,
     *     filas: list<array<string, mixed>>, totales: array<string, mixed>, nota: ?string,
     *     grafico: array{tipo: string, items: list<array<string, mixed>>, etiquetas: ?list<string>, series: ?list<array<string, mixed>>}
     * }
     */
    public function cortesEstrategicos(string $reporte, array $filtros): array
    {
        $titulo = self::REPORTES[$reporte] ?? null;

        if ($titulo === null) {
            throw new RuntimeException("Reporte desconocido: {$reporte}");
        }

        $tabla = match ($reporte) {
            'por-categoria' => $this->reporteAgrupado($filtros, 'categoria', $titulo),
            'por-ubicacion' => $this->reporteAgrupado($filtros, 'ubicacion', $titulo),
            'antiguedad' => $this->reporteAntiguedad($filtros, $titulo),
            'garantias' => $this->reporteGarantias($filtros, $titulo),
            'mantenimiento' => $this->reporteMantenimiento($filtros, $titulo),
            'rotacion' => $this->reporteRotacion($filtros, $titulo),
            'costos' => $this->reporteCostos($filtros, $titulo),
        };

        $tabla['clave'] = $reporte;
        $tabla['titulo'] = $titulo;

        return $tabla;
    }

    /**
     * Descarga del módulo: CSV para el resumen de KPIs y PDF (dompdf, con
     * su gráfico SVG embebido) para los 7 reportes estratégicos.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     */
    public function exportar(string $reporte, array $filtros, ?string $usuario = null): StreamedResponse|Response
    {
        if ($reporte === self::KPI_RESUMEN) {
            return $this->reportes->exportarFilas(
                'kpi-resumen-'.now()->format('Y-m-d').'.csv',
                ['Indicador', 'Valor'],
                $this->filasKpiResumen($filtros),
            );
        }

        $tabla = $this->cortesEstrategicos($reporte, $filtros);

        return Pdf::loadView('indicadores.reporte-pdf', [
            'titulo' => $tabla['titulo'],
            'resumen' => $this->filtrosResumen($filtros),
            'columnas' => $tabla['columnas'],
            'filas' => $tabla['filas'],
            'totales' => $tabla['totales'],
            'nota' => $tabla['nota'],
            'grafico' => $tabla['grafico'],
            'emitida_en' => now(),
            'emitida_por' => $usuario,
        ])
            ->setPaper('letter')
            ->download(Str::slug($tabla['titulo']).'-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Categorías distintas presentes en el inventario (para el filtro).
     *
     * @return Collection<int, string>
     */
    public function categoriasDisponibles(): Collection
    {
        return Activo::query()
            ->whereNotNull('Categoria')
            ->distinct()
            ->orderBy('Categoria')
            ->pluck('Categoria');
    }

    /**
     * Grupos de estado disponibles: el catálogo canónico más los valores
     * denormalizados legados de `ACTIVOS.Estado`.
     *
     * @return Collection<int, string>
     */
    public function gruposDisponibles(): Collection
    {
        return collect(EstadoActivo::GRUPOS)
            ->merge(Activo::query()->whereNotNull('Estado')->distinct()->pluck('Estado'))
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Resumen legible de los filtros aplicados (encabezado del PDF).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     */
    public function filtrosResumen(array $filtros): string
    {
        $partes = [
            'Del '.Carbon::parse($filtros['desde'])->format('d/m/Y')
                .' al '.Carbon::parse($filtros['hasta'])->format('d/m/Y'),
        ];

        if ($filtros['categoria'] !== null) {
            $partes[] = 'Categoría: '.$filtros['categoria'];
        }

        if ($filtros['grupo'] !== null) {
            $partes[] = 'Grupo: '.$filtros['grupo'];
        }

        return implode(' · ', $partes);
    }

    /**
     * Filas del CSV de KPIs (`kpi-resumen-YYYY-mm-dd.csv`).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return list<array{0: string, 1: mixed}>
     */
    public function filasKpiResumen(array $filtros): array
    {
        $kpis = $this->kpis($filtros);

        return [
            ['Periodo desde', $filtros['desde']],
            ['Periodo hasta', $filtros['hasta']],
            ['Filtro de categoría', $filtros['categoria'] ?? 'Todos'],
            ['Filtro de grupo', $filtros['grupo'] ?? 'Todos'],
            ['Equipos registrados', $kpis['equipos']],
            ['Equipos asignados', $kpis['asignados']],
            ['Tasa de utilización (%)', $kpis['utilizacion']],
            ['Equipos disponibles', $kpis['disponibles']],
            ['Equipos en reparación o defectuosos', $kpis['reparacion']],
            ['Garantías por vencer (30 días)', $kpis['garantias_por_vencer']],
            ['Garantías vencidas', $kpis['garantias_vencidas']],
            ['Costo total del inventario', $kpis['costo_total']],
            ['Altas en el periodo', $kpis['altas']],
            ['Bajas en el periodo', $kpis['bajas']],
            ['Mantenimientos en el periodo', $kpis['mantenimientos']],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Stock y movimientos
    |--------------------------------------------------------------------------
    */

    /**
     * Distribución del inventario por grupo de estado (bucket «Otros» para
     * grupos desconocidos o activos sin catálogo).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return list<array{clave: string, label: string, valor: int, color: string}>
     */
    private function distribucionPorEstado(array $filtros): array
    {
        $totales = $this->baseActivos($filtros)
            ->selectRaw(self::GRUPO_SQL.' as grupo, count(*) as total')
            ->groupByRaw(self::GRUPO_SQL)
            ->pluck('total', 'grupo')
            ->all();

        $orden = [
            'Disponible' => ['Disponibles', '#10b981'],
            'Asignado' => ['Asignados', '#0ea5e9'],
            'Reparacion' => ['En reparación', '#f59e0b'],
            'Defectuoso' => ['Defectuosos', '#f97316'],
            'Desincorporado' => ['Desincorporados', '#f43f5e'],
        ];

        $filas = [];
        $otros = 0;

        foreach ($totales as $grupo => $total) {
            if (isset($orden[$grupo])) {
                continue;
            }

            $otros += (int) $total;
        }

        foreach ($orden as $clave => [$etiqueta, $color]) {
            if (isset($totales[$clave])) {
                $filas[] = [
                    'clave' => $clave,
                    'label' => $etiqueta,
                    'valor' => (int) $totales[$clave],
                    'color' => $color,
                ];
            }
        }

        if ($otros > 0) {
            $filas[] = ['clave' => 'Otros', 'label' => 'Otros / sin estado', 'valor' => $otros, 'color' => '#64748b'];
        }

        return $filas;
    }

    /**
     * Ventana de garantías por vencer, igual que las alertas:
     * `zaico.alertas.garantia_dias` días desde hoy.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array{0: int, 1: int} [por vencer, vencidas]
     */
    private function contarGarantias(array $filtros): array
    {
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays((int) config('zaico.alertas.garantia_dias', 30));

        $porVencer = 0;
        $vencidas = 0;

        $this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros)
            ->select(
                'COMPRAS.id',
                'COMPRAS.Garantia',
                'COMPRAS.Fecha_Compra',
                'COMPRAS.Tiempo_garantia',
            )
            // Prefiltro en SQL: la garantía calculada nunca vence antes de la
            // compra (mismo criterio que `AlertaService`), así no se cargan
            // en memoria las COMPRAS fuera del horizonte.
            ->whereNotNull('COMPRAS.Fecha_Compra')
            ->where('COMPRAS.Fecha_Compra', '<=', $limite->toDateString())
            ->orderBy('COMPRAS.id')
            ->lazy(500)
            ->each(function (Compra $compra) use (&$porVencer, &$vencidas, $hoy, $limite): void {
                $vence = $this->vencimientoGarantia($compra);

                if ($vence === null) {
                    return;
                }

                if ($vence->lessThan($hoy)) {
                    $vencidas++;
                } elseif (! $vence->greaterThan($limite)) {
                    $porVencer++;
                }
            });

        return [$porVencer, $vencidas];
    }

    /*
    |--------------------------------------------------------------------------
    | Reportes estratégicos
    |--------------------------------------------------------------------------
    */

    /**
     * Inventario agregado por dimensión (`categoria` o `ubicacion`).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array<string, mixed>
     */
    private function reporteAgrupado(array $filtros, string $dimension, string $titulo): array
    {
        $esCategoria = $dimension === 'categoria';
        $claveSql = $esCategoria ? $this->categoriaSql() : $this->ubicacionSql();
        $primeraColumna = $esCategoria ? 'categoria' : 'ubicacion';

        $totales = $this->baseActivos($filtros)
            ->selectRaw($claveSql.' as clave, count(*) as equipos')
            ->groupByRaw($claveSql)
            ->pluck('equipos', 'clave');

        $porGrupo = $this->baseActivos($filtros)
            ->selectRaw($claveSql.' as clave, '.self::GRUPO_SQL.' as grupo, count(*) as valor')
            ->groupByRaw($claveSql.', '.self::GRUPO_SQL)
            ->toBase()
            ->get()
            ->groupBy('clave');

        $asignados = $this->baseActivos($filtros)
            ->whereHas('asignaciones', fn ($query) => $query->where('Estado', 'Activa'))
            ->selectRaw($claveSql.' as clave, count(*) as valor')
            ->groupByRaw($claveSql)
            ->pluck('valor', 'clave');

        $costos = $this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros)
            ->selectRaw($claveSql.' as clave, SUM('.$this->columnaSql('COMPRAS.Costo_compra').') as valor')
            ->groupByRaw($claveSql)
            ->pluck('valor', 'clave');

        $filas = [];

        foreach ($totales as $clave => $equipos) {
            $grupos = $porGrupo->get($clave, collect());

            $fila = [
                $primeraColumna => (string) $clave,
                'equipos' => (int) $equipos,
                'asignados' => (int) ($asignados[$clave] ?? 0),
                'disponibles' => $this->valorGrupo($grupos, 'Disponible'),
                'costo' => round((float) ($costos[$clave] ?? 0), 2),
            ];

            if ($esCategoria) {
                $fila['reparacion'] = $this->valorGrupo($grupos, 'Reparacion') + $this->valorGrupo($grupos, 'Defectuoso');
                $fila['desincorporados'] = $this->valorGrupo($grupos, 'Desincorporado');
            }

            $filas[] = $fila;
        }

        usort($filas, fn (array $a, array $b) => [$b['equipos'], $a[$primeraColumna]] <=> [$a['equipos'], $b[$primeraColumna]]);

        $columnas = $esCategoria
            ? [
                ['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'],
                ['clave' => 'equipos', 'etiqueta' => 'Equipos', 'tipo' => 'numero'],
                ['clave' => 'asignados', 'etiqueta' => 'Asignados', 'tipo' => 'numero'],
                ['clave' => 'disponibles', 'etiqueta' => 'Disponibles', 'tipo' => 'numero'],
                ['clave' => 'reparacion', 'etiqueta' => 'En reparación', 'tipo' => 'numero'],
                ['clave' => 'desincorporados', 'etiqueta' => 'Desincorporados', 'tipo' => 'numero'],
                ['clave' => 'costo', 'etiqueta' => 'Costo', 'tipo' => 'dinero'],
            ]
            : [
                ['clave' => 'ubicacion', 'etiqueta' => 'Ubicación', 'tipo' => 'texto'],
                ['clave' => 'equipos', 'etiqueta' => 'Equipos', 'tipo' => 'numero'],
                ['clave' => 'asignados', 'etiqueta' => 'Asignados', 'tipo' => 'numero'],
                ['clave' => 'disponibles', 'etiqueta' => 'Disponibles', 'tipo' => 'numero'],
                ['clave' => 'costo', 'etiqueta' => 'Costo', 'tipo' => 'dinero'],
            ];

        $items = $filas;
        usort($items, fn (array $a, array $b) => $b['equipos'] <=> $a['equipos']);
        $items = array_map(
            fn (array $fila) => ['etiqueta' => $fila[$primeraColumna], 'valor' => $fila['equipos']],
            array_slice($items, 0, 8),
        );

        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $this->sumarTotales($columnas, $filas),
            'nota' => null,
            'grafico' => ['tipo' => 'barras', 'items' => $items, 'etiquetas' => null, 'series' => null],
        ];
    }

    /**
     * Antigüedad del inventario por rangos de años desde el registro.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array<string, mixed>
     */
    private function reporteAntiguedad(array $filtros, string $titulo): array
    {
        $anios = $this->aniosSql('ACTIVOS.Creado_el');

        $conteos = $this->baseActivos($filtros)
            ->selectRaw(
                'SUM(CASE WHEN '.$this->columnaSql('ACTIVOS.Creado_el').' IS NULL THEN 1 ELSE 0 END) as sin_fecha, '
                .'SUM(CASE WHEN '.$anios.' < 1 THEN 1 ELSE 0 END) as menores_uno, '
                .'SUM(CASE WHEN '.$anios.' >= 1 AND '.$anios.' < 3 THEN 1 ELSE 0 END) as uno_tres, '
                .'SUM(CASE WHEN '.$anios.' >= 3 AND '.$anios.' < 5 THEN 1 ELSE 0 END) as tres_cinco, '
                .'SUM(CASE WHEN '.$anios.' >= 5 THEN 1 ELSE 0 END) as cinco_mas'
            )
            ->toBase()
            ->first();

        $rangos = [
            ['rango' => 'Menos de 1 año', 'equipos' => (int) ($conteos->menores_uno ?? 0)],
            ['rango' => '1 a 3 años', 'equipos' => (int) ($conteos->uno_tres ?? 0)],
            ['rango' => '3 a 5 años', 'equipos' => (int) ($conteos->tres_cinco ?? 0)],
            ['rango' => '5 años o más', 'equipos' => (int) ($conteos->cinco_mas ?? 0)],
        ];

        $sinFecha = (int) ($conteos->sin_fecha ?? 0);

        if ($sinFecha > 0) {
            $rangos[] = ['rango' => 'Sin fecha de registro', 'equipos' => $sinFecha];
        }

        $total = array_sum(array_column($rangos, 'equipos'));

        if ($total === 0) {
            $rangos = [];
        } else {
            foreach ($rangos as &$fila) {
                $fila['porcentaje'] = round($fila['equipos'] / $total * 100, 1);
            }
            unset($fila);
        }

        $columnas = [
            ['clave' => 'rango', 'etiqueta' => 'Antigüedad', 'tipo' => 'texto'],
            ['clave' => 'equipos', 'etiqueta' => 'Equipos', 'tipo' => 'numero'],
            ['clave' => 'porcentaje', 'etiqueta' => '% del inventario', 'tipo' => 'porcentaje'],
        ];

        $totales = $this->sumarTotales($columnas, $rangos);

        if ($total > 0) {
            $totales['porcentaje'] = 100.0;
        }

        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $rangos,
            'totales' => $totales,
            'nota' => 'Antigüedad calculada desde la fecha de registro de cada equipo.',
            'grafico' => [
                'tipo' => 'barras',
                'items' => array_map(fn (array $fila) => ['etiqueta' => $fila['rango'], 'valor' => $fila['equipos']], $rangos),
                'etiquetas' => null,
                'series' => null,
            ],
        ];
    }

    /**
     * Estado de garantías de todos los equipos con registro de compra.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array<string, mixed>
     */
    private function reporteGarantias(array $filtros, string $titulo): array
    {
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays((int) config('zaico.alertas.garantia_dias', 30));

        $filas = [];

        $this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros)
            ->select(
                'COMPRAS.Garantia',
                'COMPRAS.Fecha_Compra',
                'COMPRAS.Tiempo_garantia',
                'ACTIVOS.Etiqueta_activo',
                'ACTIVOS.Nombre_de_activo',
                'ACTIVOS.Categoria',
            )
            ->orderBy('ACTIVOS.Etiqueta_activo')
            ->lazy(500)
            ->each(function (Compra $compra) use (&$filas, $hoy, $limite): void {
                $vence = $this->vencimientoGarantia($compra);

                $estado = match (true) {
                    $vence === null => 'Sin datos',
                    $vence->lessThan($hoy) => 'Vencida',
                    $vence->lessThanOrEqualTo($limite) => 'Por vencer',
                    default => 'Vigente',
                };

                $filas[] = [
                    'etiqueta' => $compra->Etiqueta_activo,
                    'equipo' => $compra->Nombre_de_activo,
                    'categoria' => $compra->Categoria ?? '—',
                    'compra' => $compra->Fecha_Compra?->toDateString(),
                    'vencimiento' => $vence?->toDateString(),
                    'garantia' => $estado,
                ];
            });

        usort(
            $filas,
            fn (array $a, array $b) => [
                self::ORDEN_GARANTIA[$a['garantia']],
                $a['vencimiento'] ?? '9999-99-99',
            ] <=> [
                self::ORDEN_GARANTIA[$b['garantia']],
                $b['vencimiento'] ?? '9999-99-99',
            ],
        );

        $conteo = [];
        foreach ($filas as $fila) {
            $conteo[$fila['garantia']] = ($conteo[$fila['garantia']] ?? 0) + 1;
        }

        $items = [];
        foreach (self::ORDEN_GARANTIA as $estado => $_) {
            if (isset($conteo[$estado])) {
                $items[] = ['label' => $estado, 'valor' => $conteo[$estado], 'color' => self::COLORES_GARANTIA[$estado]];
            }
        }

        $columnas = [
            ['clave' => 'etiqueta', 'etiqueta' => 'Etiqueta', 'tipo' => 'texto'],
            ['clave' => 'equipo', 'etiqueta' => 'Equipo', 'tipo' => 'texto'],
            ['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'],
            ['clave' => 'compra', 'etiqueta' => 'Compra', 'tipo' => 'fecha'],
            ['clave' => 'vencimiento', 'etiqueta' => 'Vencimiento', 'tipo' => 'fecha'],
            ['clave' => 'garantia', 'etiqueta' => 'Estado', 'tipo' => 'texto'],
        ];

        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => [],
            'nota' => count($filas).' equipos con registro de compra. Ventana de «por vencer»: '
                .config('zaico.alertas.garantia_dias', 30).' días.',
            'grafico' => ['tipo' => 'donut', 'items' => $items, 'etiquetas' => null, 'series' => null],
        ];
    }

    /**
     * Equipos con más mantenimientos dentro del periodo (máx. 25).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array<string, mixed>
     */
    private function reporteMantenimiento(array $filtros, string $titulo): array
    {
        $rango = $this->rango($filtros);

        $filas = $this->conActivo(Mantenimiento::query(), 'MANTENIMIENTOS.fk_activo', $filtros)
            ->whereBetween('MANTENIMIENTOS.Fecha_mantenimiento', [$rango['desde'], $rango['hasta']])
            ->selectRaw(
                $this->columnaSql('ACTIVOS.Etiqueta_activo').' as etiqueta, '
                .$this->columnaSql('ACTIVOS.Nombre_de_activo').' as equipo, '
                .$this->columnaSql('ACTIVOS.Categoria').' as categoria, '
                .'count(*) as mantenimientos, '
                .'MAX('.$this->columnaSql('MANTENIMIENTOS.Fecha_mantenimiento').') as ultimo'
            )
            ->groupByRaw(
                $this->columnaSql('ACTIVOS.Etiqueta_activo').', '
                .$this->columnaSql('ACTIVOS.Nombre_de_activo').', '
                .$this->columnaSql('ACTIVOS.Categoria')
            )
            ->orderByDesc('mantenimientos')
            ->orderBy('etiqueta')
            ->limit(25)
            ->toBase()
            ->get()
            ->map(fn (object $fila) => [
                'etiqueta' => $fila->etiqueta,
                'equipo' => $fila->equipo,
                'categoria' => $fila->categoria ?? '—',
                'mantenimientos' => (int) $fila->mantenimientos,
                'ultimo' => $fila->ultimo,
            ])
            ->all();

        $columnas = [
            ['clave' => 'etiqueta', 'etiqueta' => 'Etiqueta', 'tipo' => 'texto'],
            ['clave' => 'equipo', 'etiqueta' => 'Equipo', 'tipo' => 'texto'],
            ['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'],
            ['clave' => 'mantenimientos', 'etiqueta' => 'Mantenimientos', 'tipo' => 'numero'],
            ['clave' => 'ultimo', 'etiqueta' => 'Último', 'tipo' => 'fecha'],
        ];

        $items = array_map(
            fn (array $fila) => ['etiqueta' => $fila['etiqueta'], 'valor' => $fila['mantenimientos']],
            array_slice($filas, 0, 8),
        );

        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $this->sumarTotales($columnas, $filas),
            'nota' => 'Equipos con más mantenimientos en el periodo (máx. 25).',
            'grafico' => ['tipo' => 'barras', 'items' => $items, 'etiquetas' => null, 'series' => null],
        ];
    }

    /**
     * Altas y bajas por mes con neto acumulado (mismo eje que la gráfica
     * principal de la pantalla).
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array<string, mixed>
     */
    private function reporteRotacion(array $filtros, string $titulo): array
    {
        $serie = $this->serieMensual($filtros);

        $filas = [];
        $acumulado = 0;

        foreach ($serie['claves'] as $indice => $_clave) {
            $altas = $serie['altas'][$indice];
            $bajas = $serie['bajas'][$indice];
            $neto = $altas - $bajas;
            $acumulado += $neto;

            $filas[] = [
                'mes' => $serie['etiquetas'][$indice],
                'altas' => $altas,
                'bajas' => $bajas,
                'neto' => $neto,
                'acumulado' => $acumulado,
            ];
        }

        $columnas = [
            ['clave' => 'mes', 'etiqueta' => 'Mes', 'tipo' => 'texto'],
            ['clave' => 'altas', 'etiqueta' => 'Altas', 'tipo' => 'numero'],
            ['clave' => 'bajas', 'etiqueta' => 'Bajas', 'tipo' => 'numero'],
            ['clave' => 'neto', 'etiqueta' => 'Neto', 'tipo' => 'numero'],
            ['clave' => 'acumulado', 'etiqueta' => 'Acumulado', 'tipo' => 'numero'],
        ];

        $totales = $this->sumarTotales($columnas, $filas);

        if ($filas !== []) {
            $totales['acumulado'] = end($filas)['acumulado'];
        }

        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $totales,
            'nota' => null,
            'grafico' => [
                'tipo' => 'linea',
                'items' => [],
                'etiquetas' => $serie['etiquetas'],
                'series' => [
                    ['nombre' => 'Altas', 'color' => '#10b981', 'valores' => $serie['altas']],
                    ['nombre' => 'Bajas', 'color' => '#f43f5e', 'valores' => $serie['bajas']],
                ],
            ],
        ];
    }

    /**
     * Costos del inventario por categoría y monto recuperado en bajas del
     * periodo.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array<string, mixed>
     */
    private function reporteCostos(array $filtros, string $titulo): array
    {
        $rango = $this->rango($filtros);

        $equipos = $this->baseActivos($filtros)
            ->selectRaw($this->categoriaSql().' as clave, count(*) as valor')
            ->groupByRaw($this->categoriaSql())
            ->pluck('valor', 'clave');

        $costos = $this->conActivo(Compra::query(), 'COMPRAS.fk_activo', $filtros)
            ->selectRaw($this->categoriaSql().' as clave, SUM('.$this->columnaSql('COMPRAS.Costo_compra').') as valor')
            ->groupByRaw($this->categoriaSql())
            ->pluck('valor', 'clave');

        $recuperado = $this->conActivo(Desincorporacion::query(), 'DESINCORPORACIONES.fk_activo', $filtros)
            ->where('DESINCORPORACIONES.Estado', 'Vigente')
            ->whereBetween('DESINCORPORACIONES.Fecha', [$rango['desde'], $rango['hasta']])
            ->selectRaw($this->categoriaSql().' as clave, SUM('.$this->columnaSql('DESINCORPORACIONES.Monto').') as valor')
            ->groupByRaw($this->categoriaSql())
            ->pluck('valor', 'clave');

        $claves = $equipos->keys()
            ->merge($costos->keys())
            ->merge($recuperado->keys())
            ->unique();

        $filas = [];

        foreach ($claves as $clave) {
            $cantidad = (int) ($equipos[$clave] ?? 0);
            $costo = round((float) ($costos[$clave] ?? 0), 2);

            $filas[] = [
                'categoria' => (string) $clave,
                'equipos' => $cantidad,
                'costo_total' => $costo,
                'costo_promedio' => $cantidad > 0 ? round($costo / $cantidad, 2) : 0.0,
                'recuperado' => round((float) ($recuperado[$clave] ?? 0), 2),
            ];
        }

        usort($filas, fn (array $a, array $b) => $b['costo_total'] <=> $a['costo_total']);

        $columnas = [
            ['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'],
            ['clave' => 'equipos', 'etiqueta' => 'Equipos', 'tipo' => 'numero'],
            ['clave' => 'costo_total', 'etiqueta' => 'Costo total', 'tipo' => 'dinero'],
            ['clave' => 'costo_promedio', 'etiqueta' => 'Costo promedio', 'tipo' => 'dinero'],
            ['clave' => 'recuperado', 'etiqueta' => 'Recuperado en bajas', 'tipo' => 'dinero'],
        ];

        $totales = $this->sumarTotales($columnas, $filas);

        if (isset($totales['costo_total'], $totales['equipos'])) {
            $totales['costo_promedio'] = $totales['equipos'] > 0
                ? round($totales['costo_total'] / $totales['equipos'], 2)
                : 0.0;
        }

        $items = array_map(
            fn (array $fila) => ['etiqueta' => $fila['categoria'], 'valor' => $fila['costo_total']],
            array_slice($filas, 0, 8),
        );

        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $totales,
            'nota' => 'El monto recuperado corresponde a bajas vigentes dentro del periodo.',
            'grafico' => ['tipo' => 'barras', 'items' => $items, 'etiquetas' => null, 'series' => null],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    /**
     * Fila de totales genérica: la primera columna de texto muestra «Total»,
     * la segunda pasa en blanco y las numéricas suman. Los llamadores
     * sobrescriben lo que no encaje (p. ej. promedios).
     *
     * @param  list<array{clave: string, etiqueta: string, tipo: string}>  $columnas
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function sumarTotales(array $columnas, array $filas): array
    {
        $totales = [];
        $primero = true;

        foreach ($columnas as $columna) {
            if ($columna['tipo'] === 'texto') {
                $totales[$columna['clave']] = $primero ? 'Total' : null;
                $primero = false;

                continue;
            }

            if ($columna['tipo'] === 'fecha') {
                $totales[$columna['clave']] = null;

                continue;
            }

            $totales[$columna['clave']] = collect($filas)->sum($columna['clave']);
        }

        return $totales;
    }

    /**
     * Suma del valor de un grupo dentro de la colección agrupada.
     *
     * @param  Collection<int, object>  $filas
     */
    private function valorGrupo(Collection $filas, string $grupo): int
    {
        return (int) ($filas->firstWhere('grupo', $grupo)->valor ?? 0);
    }

    /**
     * Deja los `n` primeros agrupados (en orden) y acumula el resto en una
     * fila «Otros», para que las gráficas no se saturen.
     *
     * @param  Collection<array-key, mixed>  $agrupado
     * @return list<array{etiqueta: string, valor: int|float}>
     */
    private function conResto(Collection $agrupado, int $limite, string $etiquetaResto): array
    {
        $filas = [];
        $resto = 0;
        $posicion = 0;

        foreach ($agrupado as $clave => $valor) {
            $posicion++;
            $valor = is_numeric($valor) ? $valor + 0 : 0;

            if ($posicion <= $limite) {
                $filas[] = ['etiqueta' => (string) $clave, 'valor' => $valor];
            } else {
                $resto += $valor;
            }
        }

        if ($resto > 0) {
            $filas[] = ['etiqueta' => $etiquetaResto, 'valor' => $resto];
        }

        return $filas;
    }

    /**
     * Inventario como base de consulta: LEFT JOIN al catálogo (para el
     * `COALESCE` de grupo) más los filtros comunes de categoría y grupo.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return Builder<Activo>
     */
    private function baseActivos(array $filtros): Builder
    {
        $query = Activo::query()
            ->leftJoin('ESTADOS_ACTIVO', 'ACTIVOS.fk_estado', '=', 'ESTADOS_ACTIVO.id');

        return $this->aplicarFiltrosActivos($query, $filtros);
    }

    /**
     * Une la tabla de activos (y su catálogo de estados) a una consulta
     * partente de otra entidad, y aplica los filtros comunes.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return Builder<*>
     */
    private function conActivo(Builder $query, string $columnaFk, array $filtros): Builder
    {
        $query
            ->join('ACTIVOS', $columnaFk, '=', 'ACTIVOS.id')
            ->leftJoin('ESTADOS_ACTIVO', 'ACTIVOS.fk_estado', '=', 'ESTADOS_ACTIVO.id');

        return $this->aplicarFiltrosActivos($query, $filtros);
    }

    /**
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return Builder<*>
     */
    private function aplicarFiltrosActivos(Builder $query, array $filtros): Builder
    {
        if ($filtros['categoria'] !== null) {
            $query->where('ACTIVOS.Categoria', $filtros['categoria']);
        }

        if ($filtros['grupo'] !== null) {
            $query->whereRaw(self::GRUPO_SQL, [$filtros['grupo']]);
        }

        return $query;
    }

    /**
     * Agrega una columna de fecha por mes ('Y-m') dentro del rango.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return Collection<string, int|float>
     */
    private function agregarPorMes(Builder $query, string $columna, array $filtros, string $expresion, bool $exclusivo = false): Collection
    {
        $rango = $this->rango($filtros);
        $sqlMes = $this->mesSql($this->columnaSql($columna));

        return $query
            ->selectRaw($sqlMes.' as mes, '.$expresion.' as valor')
            ->groupByRaw($sqlMes)
            ->when($exclusivo,
                fn ($q) => $q
                    ->where($columna, '>=', $rango['desde'])
                    ->where($columna, '<', $rango['hasta_exclusivo']),
                fn ($q) => $q->whereBetween($columna, [$rango['desde'], $rango['hasta']]))
            ->pluck('valor', 'mes');
    }

    /**
     * Meses ('Y-m') entre el inicio del periodo y su fin.
     *
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return list<string>
     */
    private function meses(array $filtros): array
    {
        $meses = [];
        $actual = Carbon::parse($filtros['desde'])->startOfMonth();
        $final = Carbon::parse($filtros['hasta'])->startOfMonth();

        // Tope de cortesía: evita bucles absurdos ante rangos decenales.
        while ($actual->lessThanOrEqualTo($final) && count($meses) < 600) {
            $meses[] = $actual->format('Y-m');
            $actual->addMonth();
        }

        return $meses;
    }

    /**
     * Etiqueta corta de mes para los ejes: «ene 26».
     */
    private function etiquetaMes(string $clave): string
    {
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $numero = (int) substr($clave, 5, 2);

        return ($meses[$numero - 1] ?? '?').' '.substr($clave, 2, 2);
    }

    /**
     * @param  array{desde: string, hasta: string, categoria: ?string, grupo: ?string}  $filtros
     * @return array{desde: string, hasta: string, hasta_exclusivo: string}
     */
    private function rango(array $filtros): array
    {
        return [
            'desde' => $filtros['desde'],
            'hasta' => $filtros['hasta'],
            // `Creado_el` es timestamp: el extremo superior es exclusivo para
            // incluir todo el día «hasta» en ambos motores.
            'hasta_exclusivo' => Carbon::parse($filtros['hasta'])->addDay()->toDateString(),
        ];
    }

    /**
     * Extracción de 'Y-m' de una fecha. `substr(x::text, ...)` solo existe en
     * PostgreSQL con cast explícito; SQLite trabaja sobre texto directamente.
     */
    private function mesSql(string $columna): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return 'substr('.$columna.'::text, 1, 7)';
        }

        return 'substr('.$columna.', 1, 7)';
    }

    /**
     * Edad en años (decimal) de una fecha hasta hoy. Expresión condicionada
     * al motor: `julianday` es de SQLite y `EXTRACT(EPOCH ...)` de PostgreSQL.
     */
    private function aniosSql(string $columna): string
    {
        $columna = $this->columnaSql($columna);

        if (DB::getDriverName() === 'pgsql') {
            return '(EXTRACT(EPOCH FROM (now() - '.$columna.')) / 31557600)';
        }

        return "((julianday('now') - julianday(".$columna.')) / 365.25)';
    }

    /**
     * Categoría del activo con bucket «Sin categoría».
     */
    private function categoriaSql(): string
    {
        return 'COALESCE('.$this->columnaSql('ACTIVOS.Categoria').", 'Sin categoría')";
    }

    /**
     * Ubicación del activo con bucket «Sin ubicación».
     */
    private function ubicacionSql(): string
    {
        return 'COALESCE('.$this->columnaSql('ACTIVOS.Ubicacion').", 'Sin ubicación')";
    }

    /**
     * `Tabla.Columna` → `"Tabla"."Columna"` (identificadores seguros en
     * PostgreSQL y SQLite).
     */
    private function columnaSql(string $columna): string
    {
        $partes = array_map(fn (string $parte) => '"'.$parte.'"', explode('.', $columna));

        return implode('.', $partes);
    }
}
