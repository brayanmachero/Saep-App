<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StopObservacion extends Model
{
    protected $table = 'stop_observaciones';

    protected $guarded = ['id'];

    protected $casts = [
        'marca_temporal' => 'datetime',
        'fecha_tarjeta'  => 'date',
        'checklist_data' => 'array',
    ];
}
