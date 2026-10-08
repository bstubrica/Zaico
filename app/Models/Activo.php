<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Activo extends Model
{
    protected $table = 'ACTIVOS';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'Nombre_de_activo',
        'Ubicacion',
        'Ubicacion_Predeterminada',
        'Serial',
        'Fabricante',
        'Categoria',
        'Modelo',
        'Observaciones',
        'Etiqueta_activo',
        'Estado',
        'Direccion_MAC',
        'Imagen_Activo',
        'fk_estado',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Creado_el' => 'datetime',
        'Actualizado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $activo) => $activo->Creado_el = now());
        static::updating(fn (self $activo) => $activo->Actualizado_el = now());
    }

    public function estadoCatalogo(): BelongsTo
    {
        return $this->belongsTo(EstadoActivo::class, 'fk_estado', 'id');
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'fk_Activo', 'id');
    }

    public function asignacionActiva(): HasOne
    {
        return $this->hasOne(Asignacion::class, 'fk_Activo', 'id')
            ->where('Estado', 'Activa');
    }

    /**
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'fk_activo', 'id');
    }

    public function compra(): HasOne
    {
        return $this->hasOne(Compra::class, 'fk_activo', 'id');
    }

    /**
     * @return HasMany<HistorialEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(HistorialEvento::class, 'fk_activo', 'id');
    }

    public function getAsignadoAAttribute(): ?string
    {
        $asignacion = $this->asignacionActiva;

        if (! $asignacion || ! $asignacion->personal) {
            return null;
        }

        return trim($asignacion->personal->Nombre.' '.$asignacion->personal->Apellido);
    }
}
