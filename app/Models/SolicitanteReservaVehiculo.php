<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitanteReservaVehiculo extends Model
{
    protected $table = 'solicitantes_reserva_vehiculo';

    protected $guarded = ['id'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
