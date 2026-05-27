<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaSaldoVacaciones extends Model
{
    protected $table = 'talana_saldo_vacaciones';

    protected $fillable = [
        'empleado_id',
        'rut',
        'nombre',
        'fecha_corte',
        'dias_normales',
        'dias_progresivos',
        'dias_restantes',
        'dias_zona_extrema',
        'tiene_error',
        'synced_at',
    ];

    protected $casts = [
        'fecha_corte'      => 'date',
        'dias_normales'    => 'float',
        'dias_progresivos' => 'float',
        'dias_restantes'   => 'float',
        'dias_zona_extrema'=> 'float',
        'tiene_error'      => 'boolean',
    ];
}
