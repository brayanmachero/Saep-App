<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaContrato extends Model
{
    protected $table = 'talana_contratos';

    protected $fillable = [
        'talana_id', 'empresa_id', 'empresa_nombre',
        'persona_talana_id', 'persona_nombre', 'persona_rut',
        'persona_email', 'persona_fecha_nacimiento', 'tipo_contrato', 'tipo_contrato_nombre',
        'fecha_contratacion', 'desde', 'hasta', 'finiquitado', 'sucursal_nombre',
        'centro_costo_nombre', 'cargo_nombre', 'horas_jornada',
        'jefe_nombre', 'synced_at',
    ];

    protected $casts = [
        'fecha_contratacion' => 'date',
        'persona_fecha_nacimiento' => 'date',
        'desde'              => 'date',
        'hasta'              => 'date',
        'finiquitado'        => 'boolean',
        'horas_jornada'      => 'decimal:1',
        'synced_at'          => 'datetime',
    ];

    public function persona()
    {
        return $this->belongsTo(TalanaPersona::class, 'persona_talana_id', 'talana_id');
    }
}
