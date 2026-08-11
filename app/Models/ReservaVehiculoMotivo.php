<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservaVehiculoMotivo extends Model
{
    protected $table = 'reserva_vehiculo_motivos';

    protected $guarded = ['id'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
