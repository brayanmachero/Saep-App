<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstPlanAccion extends Model
{
    protected $table = 'sst_plan_accion';

    protected $fillable = ['actividad_id','accion','responsable','fecha_compromiso','estado','observacion','creado_por'];

    protected $casts = ['fecha_compromiso' => 'date'];

    public function actividad()  { return $this->belongsTo(SstActividad::class, 'actividad_id'); }
    public function creadoPor()  { return $this->belongsTo(User::class, 'creado_por'); }

    public function getEstadoBadgeAttribute(): array
    {
        return match($this->estado) {
            'PENDIENTE'    => ['label'=>'Pendiente',    'class'=>'badge-warning'],
            'EN_PROGRESO'  => ['label'=>'En Progreso',  'class'=>'badge-info'],
            'COMPLETADO'   => ['label'=>'Completado',   'class'=>'badge-success'],
            'CANCELADO'    => ['label'=>'Cancelado',    'class'=>'badge-danger'],
            default        => ['label'=>$this->estado,  'class'=>'badge-secondary'],
        };
    }
}
