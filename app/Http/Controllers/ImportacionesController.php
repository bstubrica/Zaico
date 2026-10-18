<?php

namespace App\Http\Controllers;

use App\Http\Requests\EliminarImportacionesRequest;
use App\Http\Requests\ImportarCsvRequest;
use App\Models\ImportacionLog;
use App\Services\ImportacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

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
            // Importación rechazada antes de crear el log: no dejar el CSV
            // temporal huérfano hasta que pase el depurador diario.
            Storage::disk('local')->delete($ruta);

            return back()->withErrors(['archivo' => $e->getMessage()]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($ruta);

            throw $e;
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
        Storage::disk('local')->delete($ruta);

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

    public function destroy(ImportacionLog $importacion): RedirectResponse
    {
        $this->importaciones->eliminar($importacion);

        return back()->with('success', 'Registro de importación eliminado.');
    }

    public function destroyMasivo(EliminarImportacionesRequest $request): RedirectResponse
    {
        $ids = $request->boolean('todos')
            ? $this->importaciones->idsDeFiltro()
            : ($request->validated('ids') ?? []);

        if ($ids === []) {
            return back()->with('error', 'No hay registros de importación por eliminar.');
        }

        if (count($ids) > ImportacionService::MAXIMO_LOTE) {
            return back()->with('error', 'La selección supera el límite de '.ImportacionService::MAXIMO_LOTE.' registros por operación.');
        }

        $resultado = $this->importaciones->eliminarLote($ids);

        $redirect = back()
            ->with('success', "Registros de importación eliminados: {$resultado['eliminados']} de ".count($ids).' seleccionados.');

        if ($resultado['omitidos'] !== []) {
            $redirect
                ->with('error', 'Algunos registros no se procesaron (ver detalle).')
                ->with('lote_omitidos', $resultado['omitidos']);
        }

        return $redirect;
    }
}
