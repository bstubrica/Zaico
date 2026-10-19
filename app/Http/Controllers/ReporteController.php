<?php

namespace App\Http\Controllers;

use App\Enums\EstatusServidor;
use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Services\ActivoService;
use App\Services\DesincorporacionService;
use App\Services\HistorialService;
use App\Services\MantenimientoService;
use App\Services\PersonalService;
use App\Services\ReporteService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $reportes,
        private readonly ActivoService $activos,
        private readonly PersonalService $personal,
        private readonly HistorialService $historial,
        private readonly MantenimientoService $mantenimientos,
        private readonly DesincorporacionService $desincorporaciones,
    ) {}

    public function activos(Request $request): StreamedResponse
    {
        $filtros = $request->only(['termino', 'categoria', 'estado', 'ubicacion', 'modelo', 'asignado_a', 'ip']);

        return $this->reportes->exportar(
            'activos-'.now()->format('Y-m-d').'.csv',
            ['Etiqueta', 'Nombre', 'Serial', 'Categoría', 'Fabricante', 'Modelo', 'Ubicación', 'Estado', 'Asignado a', 'Dirección MAC', 'IP', 'Sistema operativo', 'Memoria RAM', 'Capacidad de disco', 'Estatus'],
            $this->activos->consultaListado($filtros),
            fn (Activo $activo): array => [
                $activo->Etiqueta_activo,
                $activo->Nombre_de_activo,
                $activo->Serial,
                $activo->Categoria,
                $activo->Fabricante,
                $activo->Modelo,
                $activo->Ubicacion,
                $activo->estadoCatalogo?->Nombre,
                $activo->asignacionActiva?->personal?->NombreCompleto,
                $activo->Direccion_MAC,
                $activo->IP,
                $activo->Sistema_Operativo,
                $activo->Memoria_RAM,
                $activo->Capacidad_Disco,
                EstatusServidor::etiquetaDe($activo->Estatus_Servidor),
            ],
        );
    }

    public function personal(Request $request): StreamedResponse
    {
        $filtros = $request->only(['termino', 'estado', 'ubicacion_id', 'cargo']);

        return $this->reportes->exportar(
            'personal-'.now()->format('Y-m-d').'.csv',
            ['Nombre', 'Apellido', 'Usuario', 'Cargo', 'Departamento / Ubicación', 'Estado', 'Equipos asignados'],
            $this->personal->consultaListado($filtros),
            fn (Personal $persona): array => [
                $persona->Nombre,
                $persona->Apellido,
                $persona->Nombre_usuario,
                $persona->Cargo,
                $persona->ubicacion?->Nombre ?? '—',
                $persona->Estado,
                $persona->asignaciones_activas,
            ],
        );
    }

    public function historial(Request $request): StreamedResponse
    {
        $filtros = $request->only(['desde', 'hasta', 'personal_id']);

        return $this->reportes->exportar(
            'historial-asignaciones-'.now()->format('Y-m-d').'.csv',
            ['Fecha asignación', 'Etiqueta', 'Equipo', 'Persona', 'Fecha devolución', 'Estado', 'Observaciones', 'Registrado por'],
            $this->historial->consultaAsignaciones($filtros),
            fn (Asignacion $asignacion): array => [
                $asignacion->Fecha_asignacion?->format('d/m/Y'),
                $asignacion->activo?->Etiqueta_activo,
                $asignacion->activo?->Nombre_de_activo,
                $asignacion->personal?->NombreCompleto,
                $asignacion->Fecha_devolucion?->format('d/m/Y'),
                $asignacion->Estado,
                $asignacion->Observaciones,
                $asignacion->usuario?->name,
            ],
        );
    }

    public function mantenimientos(Request $request): StreamedResponse
    {
        $filtros = $request->only(['desde', 'hasta', 'activo']);

        return $this->reportes->exportar(
            'mantenimientos-'.now()->format('Y-m-d').'.csv',
            ['Fecha', 'Etiqueta', 'Equipo', 'Tipo', 'Descripción', 'Evidencias', 'Registrado por'],
            $this->mantenimientos->consultaGlobal($filtros),
            fn (Mantenimiento $mantenimiento): array => [
                $mantenimiento->Fecha_mantenimiento?->format('d/m/Y'),
                $mantenimiento->activo?->Etiqueta_activo,
                $mantenimiento->activo?->Nombre_de_activo,
                $mantenimiento->Tipo,
                $mantenimiento->Descripcion,
                $mantenimiento->evidencias->count(),
                $mantenimiento->usuario?->name,
            ],
        );
    }

    public function desincorporados(Request $request): StreamedResponse
    {
        $filtros = $request->only(['termino', 'tipo', 'desde', 'hasta', 'sin_datos']);

        return $this->reportes->exportar(
            'desincorporados-'.now()->format('Y-m-d').'.csv',
            ['Fecha', 'Etiqueta', 'Equipo', 'Serial', 'Categoría', 'Tipo de baja', 'Destinatario', 'Detalle del destino', 'Referencia', 'Monto', 'Moneda', 'Motivo', 'Registrado por'],
            $this->desincorporaciones->consultaListado($filtros),
            fn (Activo $activo): array => [
                $activo->desincorporacion?->Fecha?->format('d/m/Y'),
                $activo->Etiqueta_activo,
                $activo->Nombre_de_activo,
                $activo->Serial,
                $activo->Categoria,
                $activo->desincorporacion?->Tipo,
                $activo->desincorporacion?->Destinatario,
                $activo->desincorporacion?->Detalle_destino,
                $activo->desincorporacion?->Referencia,
                $activo->desincorporacion?->Monto,
                $activo->desincorporacion?->Moneda,
                $activo->desincorporacion?->Motivo,
                $activo->desincorporacion?->usuario?->name,
            ],
        );
    }
}
