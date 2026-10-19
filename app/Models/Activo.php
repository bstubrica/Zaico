<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'IP',
        'Sistema_Operativo',
        'Memoria_RAM',
        'Capacidad_Disco',
        'Estatus_Servidor',
        'Imagen_Activo',
        'fk_estado',
        'fk_ubicacion',
        'fk_modelo',
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

    public function ubicacionCatalogo(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'fk_ubicacion', 'id');
    }

    public function modeloCatalogo(): BelongsTo
    {
        return $this->belongsTo(ModeloActivo::class, 'fk_modelo', 'id');
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

    /**
     * Tarjetas de red del equipo, ordenadas por número de tarjeta.
     *
     * @return HasMany<TarjetaRed, $this>
     */
    public function tarjetasRed(): HasMany
    {
        return $this->hasMany(TarjetaRed::class, 'fk_activo', 'id')->orderBy('Numero');
    }

    /**
     * ¿La categoría del activo corresponde a un servidor? `Categoria` es texto
     * libre, por eso se compara normalizada contra `config('zaico.servidores')`.
     */
    public function esServidor(): bool
    {
        return self::categoriaEsServidor($this->Categoria);
    }

    public static function categoriaEsServidor(?string $categoria): bool
    {
        return self::categoriaEnLista($categoria, 'zaico.servidores.categorias');
    }

    /**
     * ¿La categoría corresponde a un switch del inventario? Se usa para armar
     * el catálogo de switches a los que conectan las tarjetas de red.
     */
    public static function categoriaEsSwitch(?string $categoria): bool
    {
        return self::categoriaEnLista($categoria, 'zaico.servidores.categorias_switch');
    }

    private static function categoriaEnLista(?string $categoria, string $claveConfig): bool
    {
        if ($categoria === null) {
            return false;
        }

        $normalizada = Str::lower(trim($categoria));

        if ($normalizada === '') {
            return false;
        }

        return in_array($normalizada, (array) config($claveConfig, []), true);
    }

    public function compra(): HasOne
    {
        return $this->hasOne(Compra::class, 'fk_activo', 'id');
    }

    /**
     * Registro de baja más reciente del activo (si fue desincorporado).
     */
    public function desincorporacion(): HasOne
    {
        return $this->hasOne(Desincorporacion::class, 'fk_activo', 'id')
            ->orderByDesc('Fecha')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<Desincorporacion, $this>
     */
    public function desincorporaciones(): HasMany
    {
        return $this->hasMany(Desincorporacion::class, 'fk_activo', 'id');
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
