<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalRequest;
use App\Models\Personal;
use App\Services\PersonalService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PersonalController extends Controller
{
    public function __construct(private readonly PersonalService $personal) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['termino', 'estado']);
        $filtros['tamano'] = 50;
        $personas = $this->personal->listar($filtros);

        return view('personal.index', compact('personas', 'filtros'));
    }

    public function create(): View
    {
        return view('personal.create');
    }

    public function store(StorePersonalRequest $request)
    {
        $this->personal->crear($request->validated());

        return redirect()->route('personal.index')->with('success', 'Persona registrada correctamente.');
    }

    public function show(Personal $personal): View
    {
        $equipos = $this->personal->equiposAsignados($personal);

        return view('personal.show', compact('personal', 'equipos'));
    }

    public function edit(Personal $personal): View
    {
        return view('personal.edit', compact('personal'));
    }

    public function update(StorePersonalRequest $request, Personal $personal)
    {
        $this->personal->actualizar($personal, $request->validated());

        return redirect()->route('personal.index')->with('success', 'Persona actualizada.');
    }

    public function destroy(Personal $personal)
    {
        try {
            $this->personal->darDeBaja($personal);
        } catch (RuntimeException $e) {
            return back()->withErrors(['baja' => $e->getMessage()]);
        }

        return redirect()->route('personal.index')->with('success', 'Persona dada de baja.');
    }
}
