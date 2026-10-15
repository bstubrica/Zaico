<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(): View
    {
        $porGrupo = $this->dashboard->totalesPorGrupo();
        $recientes = $this->dashboard->recientes();
        $total = $this->dashboard->total();

        return view('dashboard', compact('porGrupo', 'recientes', 'total'));
    }
}
