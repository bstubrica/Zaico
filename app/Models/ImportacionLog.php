<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionLog extends Model
{
    protected $table = 'IMPORTACIONES_LOG';

    public $timestamps = false;

    protected $fillable = [
        'Nombre_archivo',
        'fk_usuario',
        'Fecha',
        'Total_filas',
        'Insertadas',
        'Actualizadas',
        'Errores',
        'Modo',
        'Estado',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Fecha' => 'datetime',
        'Total_filas' => 'integer',
        'Insertadas' => 'integer',
        'Actualizadas' => 'integer',
        'Errores' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $log) => $log->Fecha ??= now());
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_usuario', 'id');
    }

    /**
     * @return HasMany<ImportacionDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(ImportacionDetalle::class, 'fk_importacion', 'id');
    }
}
