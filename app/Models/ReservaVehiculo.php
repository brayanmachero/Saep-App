<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservaVehiculo extends Model
{
    public const ESTADOS_BLOQUEANTES = ['CONFIRMADA', 'EN_USO', 'VENCIDA'];

    public const ESTADOS = [
        'CONFIRMADA' => 'Confirmada',
        'CANCELADA' => 'Cancelada',
        'EN_USO' => 'En uso',
        'DEVUELTA' => 'Devuelta',
        'VENCIDA' => 'Vencida',
    ];

    protected $guarded = ['id'];

    protected $table = 'reservas_vehiculos';

    protected $casts = [
        'inicio' => 'datetime',
        'termino' => 'datetime',
        'cancelada_at' => 'datetime',
        'recordatorio_enviado_at' => 'datetime',
        'vencimiento_notificado_at' => 'datetime',
        'calendar_synced_at' => 'datetime',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ReservaVehiculoEvento::class)->latest();
    }

    public function estaVigente(): bool
    {
        return in_array($this->estado, self::ESTADOS_BLOQUEANTES, true);
    }
}
