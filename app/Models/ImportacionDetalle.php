<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionDetalle extends Model
{
    protected $table = 'IMPORTACIONES_DETALLE';

    public $timestamps = false;

    protected $fillable = [
        'fk_importacion',
        'Fila_numero',
        'Etiqueta',
        'Accion',
        'Mensaje',
        'Datos_previos',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fila_numero' => 'integer',
        'Datos_previos' => 'array',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionLog::class, 'fk_importacion', 'id');
    }
}
