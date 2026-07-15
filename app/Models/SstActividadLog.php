<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstActividadLog extends Model
{
    protected $table = 'sst_actividad_logs';

    protected $fillable = [
        'programa_id',
        'actividad_id',
        'user_id',
        'accion',
        'resumen',
        'cambios',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function programa()
    {
        return $this->belongsTo(ProgramaSst::class, 'programa_id');
    }

    public function actividad()
    {
        return $this->belongsTo(SstActividad::class, 'actividad_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
