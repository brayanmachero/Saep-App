<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstNotificacionLog extends Model
{
    protected $table = 'sst_notificacion_log';

    protected $fillable = [
        'actividad_id', 'user_id', 'email', 'tipo', 'mes', 'rol_destinatario',
    ];

    public function actividad() { return $this->belongsTo(SstActividad::class, 'actividad_id'); }
    public function user()      { return $this->belongsTo(User::class, 'user_id'); }
}
