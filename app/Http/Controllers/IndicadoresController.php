<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportarIndicadoresRequest;
use App\Http\Requests\IndicadoresFiltrosRequest;
use App\Services\IndicadoresService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Módulo «Reportes y KPIs» (solo lectura, cualquier usuario autenticado).
 * El controlador solo orquesta: los agregados viven en `IndicadoresService`
 * y la validación en los Form Requests.
 */
class IndicadoresController extends Controller
{
    public function __construct(private readonly IndicadoresService $indicadores) {}

    /**
     * Pantalla con KPIs, gráficos SVG y el reporte estratégico seleccionado.
     */
    public function index(IndicadoresFiltrosRequest $request): View
    {
        $filtros = $request->filtros();
        $reporte = $request->reporte();

        return view('indicadores.index', [
            'filtros' => $filtros,
            'reporte' => $reporte,
            'reportes' => IndicadoresService::REPORTES,
            'categorias' => $this->indicadores->categoriasDisponibles(),
            'grupos' => $this->indicadores->gruposDisponibles(),
            'kpis' => $this->indicadores->kpis($filtros),
            'graficos' => $this->indicadores->graficos($filtros),
            'tabla' => $this->indicadores->cortesEstrategicos($reporte, $filtros),
        ]);
    }

    /**
     * Descarga: CSV del resumen de KPIs o PDF del reporte estratégico.
     */
    public function exportar(ExportarIndicadoresRequest $request): StreamedResponse|Response
    {
        return $this->indicadores->exportar(
            $request->reporte(),
            $request->filtros(),
            $request->user()?->name,
        );
    }
}
