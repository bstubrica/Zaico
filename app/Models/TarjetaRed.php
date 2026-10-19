<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarjetaRed extends Model
{
    protected $table = 'TARJETAS_RED';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'fk_activo',
        'Numero',
        'Nombre',
        'Direccion_MAC',
        'IP',
        'fk_switch',
        'Switch',
        'Puerto',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Creado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $tarjeta) => $tarjeta->Creado_el = now());
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_activo', 'id');
    }

    /**
     * Equipo del inventario (categoría «SWITCH») al que está conectada.
     */
    public function switchCatalogo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_switch', 'id');
    }
}
