<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstSeguimiento extends Model
{
    protected $table = 'sst_seguimiento';

    protected $fillable = ['actividad_id','mes','programado','realizado','observacion','actualizado_por','fecha_actualizacion','cantidad_realizada'];

    protected $casts = ['programado'=>'boolean','realizado'=>'boolean','mes'=>'integer','cantidad_realizada'=>'integer'];

    public function actividad()      { return $this->belongsTo(SstActividad::class, 'actividad_id'); }
    public function actualizadoPor() { return $this->belongsTo(User::class, 'actualizado_por'); }
}
