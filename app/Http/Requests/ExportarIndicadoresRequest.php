<?php

namespace App\Http\Requests;

use App\Services\IndicadoresService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Exportación desde el módulo de indicadores: CSV para `kpi-resumen` y PDF
 * para los 7 reportes estratégicos (`IndicadoresService::exportar`).
 */
class ExportarIndicadoresRequest extends IndicadoresFiltrosRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = parent::rules();
        $reglas['reporte'] = ['required', 'string', Rule::in([
            ...array_keys(IndicadoresService::REPORTES),
            IndicadoresService::KPI_RESUMEN,
        ])];

        return $reglas;
    }

    /**
     * A diferencia de la pantalla, la exportación sí admite el resumen de
     * KPIs (`kpi-resumen`), que se descarga como CSV.
     */
    public function reporte(): string
    {
        $reporte = $this->input('reporte');

        return is_string($reporte) ? $reporte : parent::reporte();
    }
}
