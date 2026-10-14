<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $table = 'UBICACIONES';

    public $timestamps = false;

    protected $fillable = ['Nombre', 'Descripcion', 'Estado'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Creado_el' => 'datetime',
        'Actualizado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $ubicacion) => $ubicacion->Creado_el = now());
        static::updating(fn (self $ubicacion) => $ubicacion->Actualizado_el = now());
    }

    /**
     * @return HasMany<Activo, $this>
     */
    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'fk_ubicacion', 'id');
    }
}
