<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asignacion extends Model
{
    protected $table = 'ASIGNACIONES';

    public $timestamps = false;

    protected $fillable = [
        'fk_Activo',
        'fk_Personal',
        'fk_usuario',
        'Fecha_asignacion',
        'Fecha_devolucion',
        'Observaciones',
        'Estado',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fecha_asignacion' => 'date',
        'Fecha_devolucion' => 'date',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_Activo', 'id');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'fk_Personal', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_usuario', 'id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('Estado', 'Activa');
    }

    public function getActivaAttribute(): bool
    {
        return is_null($this->Fecha_devolucion) && $this->Estado === 'Activa';
    }
}
