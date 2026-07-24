<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservacionConductaCcu extends Model
{
    protected $table = 'observaciones_conducta_ccu';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_observacion' => 'date',
        'kizeo_created_at' => 'datetime',
        'kizeo_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
