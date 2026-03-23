<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaSst extends Model
{
    protected $table = 'auditorias_sst';

    protected $fillable = [
        'titulo','tipo','centro_costo_id','auditor_id','fecha_auditoria',
        'alcance','hallazgos','no_conformidades','recomendaciones','resultado','estado',
    ];

    protected $casts = ['fecha_auditoria'=>'date'];

    public function centroCosto() { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function auditor()     { return $this->belongsTo(User::class, 'auditor_id'); }

    public function getEstadoBadgeAttribute(): array
    {
        return match($this->estado) {
            'PLANIFICADA'  => ['label'=>'Planificada',  'class'=>'badge-secondary'],
            'EN_PROCESO'   => ['label'=>'En Proceso',   'class'=>'badge-info'],
            'COMPLETADA'   => ['label'=>'Completada',   'class'=>'badge-success'],
            'CANCELADA'    => ['label'=>'Cancelada',    'class'=>'badge-danger'],
            default        => ['label'=>$this->estado,  'class'=>'badge-secondary'],
        };
    }
}
