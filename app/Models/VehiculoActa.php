<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiculoActa extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'datos' => 'array', 'evidencias' => 'array', 'auditoria_firma' => 'array',
        'preparada_at' => 'datetime', 'firmada_at' => 'datetime', 'email_enviado_at' => 'datetime',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ReservaVehiculo::class, 'reserva_vehiculo_id');
    }

    public function getEtiquetaEstadoAttribute(): string
    {
        return match ($this->estado) {
            'PENDIENTE_FIRMA' => 'Pendiente de firma', 'FIRMADA' => 'Firmada', default => 'Borrador',
        };
    }
}
