<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaVehiculoEvento extends Model
{
    protected $table = 'reserva_vehiculo_eventos';

    protected $guarded = ['id'];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ReservaVehiculo::class, 'reserva_vehiculo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
