<?php

namespace App\Http\Requests;

use App\Services\IndicadoresService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de la pantalla de indicadores: periodo (con defecto de los
 * últimos 12 meses), categoría y grupo, más el reporte estratégico a ver.
 */
class IndicadoresFiltrosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'grupo' => ['nullable', 'string', 'max:50'],
            'reporte' => ['nullable', 'string', Rule::in(array_keys(IndicadoresService::REPORTES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'desde' => 'desde',
            'hasta' => 'hasta',
            'categoria' => 'categoría',
            'grupo' => 'grupo',
            'reporte' => 'reporte',
        ];
    }

    /**
     * Filtros normalizados y con defecto (`IndicadoresService::normalizarFiltros`):
     * un extremo vacío se deriva del otro o de «hoy».
     *
     * @return array{desde: string, hasta: string, categoria: ?string, grupo: ?string}
     */
    public function filtros(): array
    {
        return app(IndicadoresService::class)->normalizarFiltros([
            'desde' => $this->filled('desde') ? (string) $this->input('desde') : null,
            'hasta' => $this->filled('hasta') ? (string) $this->input('hasta') : null,
            'categoria' => $this->filled('categoria') ? (string) $this->input('categoria') : null,
            'grupo' => $this->filled('grupo') ? (string) $this->input('grupo') : null,
        ]);
    }

    /**
     * Reporte a mostrar; por defecto el primero de la lista.
     */
    public function reporte(): string
    {
        $reporte = $this->input('reporte');

        if (is_string($reporte) && array_key_exists($reporte, IndicadoresService::REPORTES)) {
            return $reporte;
        }

        return array_key_first(IndicadoresService::REPORTES);
    }
}
