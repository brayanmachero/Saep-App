<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    public const ESTADOS = [
        'DISPONIBLE' => 'Disponible',
        'MANTENIMIENTO' => 'Mantenimiento',
        'FUERA_SERVICIO' => 'Fuera de servicio',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'reservas_habilitadas' => 'boolean',
    ];

    public function reservas(): HasMany
    {
        return $this->hasMany(ReservaVehiculo::class)->orderByDesc('inicio');
    }

    public function getNombreOperativoAttribute(): string
    {
        return trim(collect([$this->marca, $this->modelo])->filter()->implode(' '))
            ?: ($this->nombre ?: 'Vehiculo sin modelo');
    }
}
