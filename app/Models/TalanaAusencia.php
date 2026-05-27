<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaAusencia extends Model
{
    protected $table = 'talana_ausencias';

    protected $fillable = [
        'talana_id',
        'empleado_id',
        'persona_rut',
        'persona_nombre',
        'tipo_ausencia',
        'fecha_desde',
        'fecha_hasta',
        'numero_dias',
        'aprobada',
        'rebaja_salario',
        'es_continuacion',
        'fecha_retorno',
        'numero_licencia',
        'synced_at',
    ];

    protected $casts = [
        'fecha_desde'      => 'date',
        'fecha_hasta'      => 'date',
        'fecha_retorno'    => 'date',
        'aprobada'         => 'boolean',
        'rebaja_salario'   => 'boolean',
        'es_continuacion'  => 'boolean',
    ];
}
