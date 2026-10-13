<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepurarInventarioRequest;
use App\Services\DepuracionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AdminController extends Controller
{
    public function __construct(private readonly DepuracionService $depuracion) {}

    /**
     * Panel de administración con el resumen de datos de inventario y la zona
     * de peligro para la depuración total.
     */
    public function index(): View
    {
        return view('admin.index', [
            'resumen' => $this->depuracion->resumen(),
            'configurado' => $this->depuracion->estaConfigurado(),
            'habilitado' => $this->depuracion->estaHabilitado(),
            'frase' => $this->depuracion->fraseEsperada(),
        ]);
    }

    /**
     * Ejecuta el borrado físico de todos los datos de inventario.
     */
    public function depurar(DepurarInventarioRequest $request): RedirectResponse
    {
        abort_unless(
            $this->depuracion->estaHabilitado(),
            403,
            'La depuración está deshabilitada en este entorno.',
        );

        try {
            $resultado = $this->depuracion->vaciar();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo completar la depuración: '.$e->getMessage());
        }

        $request->session()->forget('importacion_pendiente');

        // Se registra en archivo porque la base de datos acaba de ser vaciada.
        Log::warning('Depuración total del inventario ejecutada.', [
            'usuario_id' => $request->user()->id,
            'usuario_email' => $request->user()->email,
            'ip' => $request->ip(),
            'tablas' => $resultado['vaciados'],
            'archivos_eliminados' => $resultado['archivos'],
        ]);

        return redirect()
            ->route('admin.index')
            ->with(
                'success',
                "Depuración completada: {$resultado['total']} registros de inventario eliminados"
                ." y {$resultado['archivos']} archivo(s) borrado(s)."
            );
    }
}
