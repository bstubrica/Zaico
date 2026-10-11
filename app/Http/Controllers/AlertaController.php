<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Services\AlertaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function index(Request $request): View
    {
        $tipo = $request->query('tipo');

        $alertas = Alerta::query()
            ->activas()
            ->with('activo')
            ->when($tipo, fn ($query, $tipo) => $query->where('Tipo', $tipo))
            ->orderBy('Tipo')
            ->orderBy('Fecha_referencia')
            ->paginate(50)
            ->withQueryString();

        $resumen = Alerta::query()
            ->activas()
            ->selectRaw('"Tipo" as tipo, count(*) as total')
            ->groupBy('Tipo')
            ->pluck('total', 'tipo');

        return view('alertas.index', compact('alertas', 'resumen', 'tipo'));
    }

    public function revisar(AlertaService $alertas): RedirectResponse
    {
        $resultado = $alertas->generar();

        return back()->with(
            'success',
            "Revisión completada: {$resultado['nuevas']} alerta(s) nueva(s), {$resultado['resueltas']} resuelta(s), {$resultado['activas']} activa(s).",
        );
    }
}
