<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    public const TIPO_REPARACION = 'ReparacionProlongada';

    public const TIPO_GARANTIA = 'GarantiaPorVencer';

    protected $table = 'ALERTAS';

    protected $fillable = [
        'Tipo',
        'fk_activo',
        'Mensaje',
        'Fecha_referencia',
        'Estado',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fecha_referencia' => 'date',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_activo', 'id');
    }

    /**
     * @param  Builder<Alerta>  $query
     * @return Builder<Alerta>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('Estado', 'Activa');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->Tipo) {
            self::TIPO_REPARACION => 'Reparación prolongada',
            self::TIPO_GARANTIA => 'Garantía por vencer',
            default => $this->Tipo,
        };
    }
}
