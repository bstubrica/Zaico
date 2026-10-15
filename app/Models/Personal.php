<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personal extends Model
{
    protected $table = 'PERSONAL';

    public $timestamps = false;

    protected $fillable = ['Nombre', 'Apellido', 'Nombre_usuario', 'Cargo', 'fk_ubicacion', 'Observaciones', 'Estado'];

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'fk_ubicacion', 'id');
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'fk_Personal', 'id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->Nombre} {$this->Apellido}");
    }
}
