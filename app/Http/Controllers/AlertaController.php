<?php

namespace App\Http\Controllers;

use App\Services\AlertaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function __construct(private readonly AlertaService $alertas) {}

    public function index(Request $request): View
    {
        $tipo = $request->query('tipo');

        $alertas = $this->alertas->listar($tipo);
        $resumen = $this->alertas->resumenPorTipo();

        return view('alertas.index', compact('alertas', 'resumen', 'tipo'));
    }

    public function revisar(): RedirectResponse
    {
        $resultado = $this->alertas->generar();

        return back()->with(
            'success',
            "Revisión completada: {$resultado['nuevas']} alerta(s) nueva(s), {$resultado['resueltas']} resuelta(s), {$resultado['activas']} activa(s).",
        );
    }
}
