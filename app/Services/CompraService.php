<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Compra;
use Illuminate\Support\Facades\DB;

class CompraService
{
    public function __construct(private readonly HistorialService $historial) {}

    /**
     * Crea o actualiza la compra 1:1 de un activo. Deja traza en el historial.
     *
     * @param  array{fk_activo?: int, Numero_Requisicion?: string|null, Fecha_Compra: string, Proveedor: string, Costo_compra?: string|int|float|null, Tiempo_garantia?: string|null, Numero_factura?: string|null, Garantia?: string|null}  $datos
     */
    public function guardar(Activo $activo, array $datos, int $usuarioId): Compra
    {
        return DB::transaction(function () use ($activo, $datos, $usuarioId) {
            $compra = Compra::updateOrCreate(
                ['fk_activo' => $activo->id],
                [
                    'Numero_Requisicion' => $datos['Numero_Requisicion'] ?? null,
                    'Fecha_Compra' => $datos['Fecha_Compra'],
                    'Proveedor' => $datos['Proveedor'],
                    'Costo_compra' => $datos['Costo_compra'] ?? null,
                    'Tiempo_garantia' => $datos['Tiempo_garantia'] ?? null,
                    'Numero_factura' => $datos['Numero_factura'] ?? null,
                    'Garantia' => $datos['Garantia'] ?? null,
                ]
            );

            $accion = $compra->wasRecentlyCreated ? 'registrada' : 'actualizada';

            $this->historial->registrar(
                $activo->id,
                'Modificacion',
                "Compra {$accion}: {$compra->Proveedor}"
                    .($compra->Fecha_Compra?->format('d/m/Y') !== null ? " el {$compra->Fecha_Compra->format('d/m/Y')}" : ''),
                usuarioId: $usuarioId,
            );

            return $compra;
        });
    }

    public function eliminar(Activo $activo, int $usuarioId): void
    {
        DB::transaction(function () use ($activo, $usuarioId) {
            Compra::where('fk_activo', $activo->id)->delete();

            $this->historial->registrar(
                $activo->id,
                'Modificacion',
                'Compra eliminada',
                usuarioId: $usuarioId,
            );
        });
    }
}
