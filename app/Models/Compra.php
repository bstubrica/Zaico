<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compra extends Model
{
    protected $table = 'COMPRAS';

    public $timestamps = false;

    protected $fillable = [
        'fk_activo',
        'Numero_Requisicion',
        'Fecha_Compra',
        'Proveedor',
        'Costo_compra',
        'Tiempo_garantia',
        'Imagen_Factura',
        'Garantia',
        'Numero_factura',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fecha_Compra' => 'date',
        'Costo_compra' => 'decimal:2',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_activo', 'id');
    }
}
