<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mantenimiento extends Model
{
    protected $table = 'MANTENIMIENTOS';

    public $timestamps = false;

    protected $fillable = [
        'fk_activo',
        'Tipo',
        'Descripcion',
        'Fecha_mantenimiento',
        'fk_usuario',
        'Creado_el',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fecha_mantenimiento' => 'date',
        'Creado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $mantenimiento) => $mantenimiento->Creado_el ??= now());
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'fk_activo', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_usuario', 'id');
    }

    /**
     * @return HasMany<EvidenciaMantenimiento, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaMantenimiento::class, 'fk_mantenimiento', 'id');
    }
}
