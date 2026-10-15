<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Desincorporacion extends Model
{
    protected $table = 'DESINCORPORACIONES';

    public $timestamps = false;

    protected $fillable = [
        'fk_activo',
        'Tipo',
        'Destinatario',
        'Detalle_destino',
        'Referencia',
        'Monto',
        'Moneda',
        'Motivo',
        'Fecha',
        'Estado',
        'fk_usuario',
        'Creado_el',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Monto' => 'decimal:2',
        'Fecha' => 'date',
        'Creado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $desincorporacion) => $desincorporacion->Creado_el ??= now());
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_activo', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_usuario', 'id');
    }
}
