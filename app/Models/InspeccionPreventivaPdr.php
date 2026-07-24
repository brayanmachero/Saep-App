<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspeccionPreventivaPdr extends Model
{
    protected $table = 'inspecciones_preventivas_pdr';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_inspeccion' => 'date',
        'kizeo_created_at' => 'datetime',
        'kizeo_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
