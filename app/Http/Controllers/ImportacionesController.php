<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarCsvRequest;
use App\Models\ImportacionLog;
use App\Services\ImportacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

class ImportacionesController extends Controller
{
    public function __construct(private readonly ImportacionService $importaciones) {}

    public function index(Request $request): View
    {
        $historial = $this->importaciones->historialImportaciones();
        $pendiente = session('importacion_pendiente');

        return view('importaciones.index', compact('historial', 'pendiente'));
    }

    public function store(ImportarCsvRequest $request): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $dryRun = $request->input('modo', 'dryrun') === 'dryrun';

        $ruta = $archivo->store('tmp/importaciones', 'local');

        try {
            $log = $this->importaciones->importarCsv(
                Storage::disk('local')->path($ruta),
                $archivo->getClientOriginalName(),
                $dryRun,
                $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        $resumen = "Filtradas {$log->Total_filas}: {$log->Insertadas} nuevas, {$log->Actualizadas} actualizadas, {$log->Errores} errores.";

        if ($dryRun) {
            session([
                'importacion_pendiente' => [
                    'ruta' => $ruta,
                    'nombre' => $archivo->getClientOriginalName(),
                    'log_id' => $log->id,
                ],
            ]);

            return redirect()
                ->route('importaciones.show', $log)
                ->with('success', "Previsualización (no se modificó nada). {$resumen}");
        }

        session()->forget('importacion_pendiente');

        return redirect()
            ->route('importaciones.show', $log)
            ->with('success', "Importación confirmada. {$resumen}");
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $pendiente = session('importacion_pendiente');

        if ($pendiente === null || ! Storage::disk('local')->exists($pendiente['ruta'])) {
            return back()->withErrors(['archivo' => 'No hay archivo pendiente de confirmación. Suba el CSV de nuevo.']);
        }

        $ruta = $pendiente['ruta'];

        try {
            $log = $this->importaciones->importarCsv(
                Storage::disk('local')->path($ruta),
                $pendiente['nombre'],
                false,
                $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        Storage::disk('local')->delete($ruta);
        session()->forget('importacion_pendiente');

        return redirect()
            ->route('importaciones.show', $log)
            ->with('success', "Importación confirmada: {$log->Insertadas} nuevas, {$log->Actualizadas} actualizadas, {$log->Errores} errores.");
    }

    public function show(ImportacionLog $importacion): View
    {
        $importacion->load(['usuario:id,name', 'detalles']);

        return view('importaciones.show', compact('importacion'));
    }
}
