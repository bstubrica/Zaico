<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenciaMantenimiento extends Model
{
    protected $table = 'EVIDENCIAS_MANTENIMIENTO';

    public $timestamps = false;

    protected $fillable = [
        'fk_mantenimiento',
        'Ruta_archivo',
        'Nombre_original',
        'Tipo_mime',
        'Tamano_bytes',
        'Subido_el',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Tamano_bytes' => 'integer',
        'Subido_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $evidencia) => $evidencia->Subido_el ??= now());
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class, 'fk_mantenimiento', 'id');
    }
}
