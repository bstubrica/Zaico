<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEvento extends Model
{
    protected $table = 'HISTORIAL_EVENTOS';

    public $timestamps = false;

    protected $fillable = [
        'fk_activo',
        'Tipo_evento',
        'Descripcion',
        'Valor_anterior',
        'Valor_nuevo',
        'fk_usuario',
        'Fecha',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fecha' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $evento) => $evento->Fecha ??= now());
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
