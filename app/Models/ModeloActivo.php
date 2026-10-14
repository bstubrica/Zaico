<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeloActivo extends Model
{
    protected $table = 'MODELOS_ACTIVO';

    public $timestamps = false;

    protected $fillable = ['Nombre', 'Fabricante', 'Categoria', 'Estado'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Creado_el' => 'datetime',
        'Actualizado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $modelo) => $modelo->Creado_el = now());
        static::updating(fn (self $modelo) => $modelo->Actualizado_el = now());
    }

    /**
     * @return HasMany<Activo, $this>
     */
    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'fk_modelo', 'id');
    }
}
