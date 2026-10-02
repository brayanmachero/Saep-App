<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstSeguimientoOcurrencia extends Model
{
    protected $table = 'sst_seguimiento_ocurrencias';

    protected $fillable = [
        'actividad_id', 'tipo', 'fecha_programada', 'fecha_inicio', 'fecha_fin',
        'programado', 'realizado', 'observacion', 'actualizado_por', 'fecha_actualizacion',
    ];

    protected $casts = [
        'fecha_programada'    => 'date',
        'fecha_inicio'        => 'date',
        'fecha_fin'           => 'date',
        'programado'          => 'boolean',
        'realizado'           => 'boolean',
        'fecha_actualizacion' => 'datetime',
    ];

    public function actividad()
    {
        return $this->belongsTo(SstActividad::class, 'actividad_id');
    }

    public function actualizadoPor()
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }
}
