<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompraRequest;
use App\Models\Activo;
use App\Models\Compra;
use App\Services\CompraService;
use App\Services\Concerns\PaginacionAcotada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ComprasController extends Controller
{
    use PaginacionAcotada;

    public function __construct(private readonly CompraService $compras) {}

    /**
     * Listado global de compras (lectura para cualquier usuario autenticado).
     */
    public function index(Request $request): View
    {
        $compras = $this->consultaIndex($request->get('tamano'));

        return view('compras.index', compact('compras'));
    }

    /**
     * Guarda (crea o actualiza) la compra 1:1 del activo.
     */
    public function store(StoreCompraRequest $request, Activo $activo): RedirectResponse
    {
        $compra = $this->compras->guardar($activo, $request->validated(), $request->user()->id);

        $mensaje = $compra->wasRecentlyCreated
            ? 'Compra registrada correctamente.'
            : 'Compra actualizada correctamente.';

        return redirect()
            ->route('activos.show', $activo)
            ->with('success', $mensaje);
    }

    public function destroy(Activo $activo): RedirectResponse
    {
        $this->compras->eliminar($activo, request()->user()->id);

        return redirect()
            ->route('activos.show', $activo)
            ->with('success', 'Compra eliminada correctamente.');
    }

    private function consultaIndex(?string $tamano): LengthAwarePaginator
    {
        return Compra::query()
            ->with(['activo:id,Nombre_de_activo,Etiqueta_activo'])
            ->orderByDesc('Fecha_Compra')
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($tamano))
            ->withQueryString();
    }
}
