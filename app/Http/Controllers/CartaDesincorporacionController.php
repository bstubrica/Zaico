<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartaDesincorporacionRequest;
use App\Models\Desincorporacion;
use App\Services\CartaDesincorporacionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Carta de desincorporación: documento imprimible/descargable con los datos de
 * la baja y los equipos desincorporados. Puede generarse para un solo registro
 * (individual) o como acta del listado filtrado. Solo lectura.
 */
class CartaDesincorporacionController extends Controller
{
    public function __construct(private readonly CartaDesincorporacionService $cartas) {}

    public function acta(CartaDesincorporacionRequest $request): View|Response|StreamedResponse
    {
        $datos = $this->cartas->datosActa($request->validated(), $request->user()->name);

        abort_if($datos['equipos'] === [], 404, 'No hay equipos desincorporados que coincidan con los filtros.');

        return $this->cartas->render($datos, $request->formato());
    }

    public function individual(CartaDesincorporacionRequest $request, Desincorporacion $desincorporacion): View|Response|StreamedResponse
    {
        $datos = $this->cartas->datosIndividual($desincorporacion, $request->user()->name);

        return $this->cartas->render($datos, $request->formato());
    }
}
