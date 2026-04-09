<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccidenteSst extends Model
{
    use SoftDeletes;

    protected $table = 'accidentes_sst';

    protected $fillable = [
        'tipo','trabajador_id','trabajador_nombre','trabajador_rut','centro_costo_id',
        'fecha_hora_accidente','lugar','descripcion','causas','medidas_inmediatas',
        'medidas_correctivas','gravedad','dias_perdidos','reportado_mutual','numero_diat',
        'estado','registrado_por',
    ];

    protected $casts = [
        'fecha_hora_accidente' => 'datetime',
        'reportado_mutual'     => 'boolean',
        'dias_perdidos'        => 'integer',
    ];

    public function trabajador()    { return $this->belongsTo(User::class, 'trabajador_id'); }
    public function centroCosto()   { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function registradoPor() { return $this->belongsTo(User::class, 'registrado_por'); }

    public function getGravedadBadgeAttribute(): array
    {
        return match($this->gravedad) {
            'LEVE'       => ['label'=>'Leve',        'class'=>'badge-warning'],
            'GRAVE'      => ['label'=>'Grave',       'class'=>'badge-danger'],
            'FATAL'      => ['label'=>'Fatal',       'class'=>'badge-dark'],
            'SIN_LESION' => ['label'=>'Sin Lesión',  'class'=>'badge-success'],
            default      => ['label'=>$this->gravedad,'class'=>'badge-secondary'],
        };
    }
}
