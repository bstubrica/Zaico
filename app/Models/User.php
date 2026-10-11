<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function puedeEscribir(): bool
    {
        return in_array($this->role, ['Admin', 'SoporteTecnico'], true);
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'fk_usuario', 'id');
    }

    /**
     * @return HasMany<Mantenimiento, $this>
     */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'fk_usuario', 'id');
    }

    /**
     * @return HasMany<HistorialEvento, $this>
     */
    public function eventosRegistrados(): HasMany
    {
        return $this->hasMany(HistorialEvento::class, 'fk_usuario', 'id');
    }

    /**
     * @return HasMany<ImportacionLog, $this>
     */
    public function importaciones(): HasMany
    {
        return $this->hasMany(ImportacionLog::class, 'fk_usuario', 'id');
    }

    /**
     * Indica si el usuario firmó registros que dependen de él. Borrarlo
     * destruiría trazabilidad (asignaciones/mantenimientos/auditoría), por lo
     * que la baja de la cuenta debe bloquearse.
     */
    public function tieneRegistrosAsociados(): bool
    {
        return $this->asignaciones()->exists()
            || $this->mantenimientos()->exists()
            || $this->eventosRegistrados()->exists()
            || $this->importaciones()->exists();
    }
}
