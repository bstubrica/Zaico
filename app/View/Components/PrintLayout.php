<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout mínimo para documentos imprimibles (cartas, reportes en pantalla).
 * Sin navegación ni barra superior: solo el cuerpo y los estilos base.
 */
class PrintLayout extends Component
{
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('layouts.print');
    }
}
