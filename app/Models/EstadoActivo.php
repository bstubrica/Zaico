<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoActivo extends Model
{
    protected $table = 'ESTADOS_ACTIVO';

    public $timestamps = false;

    protected $fillable = ['Nombre', 'Grupo', 'Deployed', 'Deployable', 'Estado'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'Deployed' => 'boolean',
        'Deployable' => 'boolean',
        'Estado' => 'integer',
    ];

    /**
     * @return HasMany<Activo, $this>
     */
    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'fk_estado', 'id');
    }
}
