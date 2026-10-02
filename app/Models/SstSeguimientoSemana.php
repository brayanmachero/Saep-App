<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstSeguimientoSemana extends Model
{
    protected $table = 'sst_seguimiento_semanas';

    protected $fillable = [
        'actividad_id', 'mes', 'semana_inicio', 'cantidad_realizada',
        'actualizado_por', 'fecha_actualizacion',
    ];

    protected $casts = [
        'mes' => 'integer',
        'semana_inicio' => 'date',
        'cantidad_realizada' => 'integer',
        'fecha_actualizacion' => 'datetime',
    ];

    public function actividad()
    {
        return $this->belongsTo(SstActividad::class, 'actividad_id');
    }
}
