<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitaSst extends Model
{
    protected $table = 'visitas_sst';

    protected $fillable = [
        'titulo','tipo','centro_costo_id','inspector_id','fecha_visita',
        'hallazgos','medidas_correctivas','estado','fotos','firma_inspector',
    ];

    protected $casts = ['fecha_visita'=>'date','fotos'=>'array'];

    public function centroCosto() { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function inspector()   { return $this->belongsTo(User::class, 'inspector_id'); }

    public function getEstadoBadgeAttribute(): array
    {
        return match($this->estado) {
            'PROGRAMADA' => ['label'=>'Programada', 'class'=>'badge-info'],
            'REALIZADA'  => ['label'=>'Realizada',  'class'=>'badge-success'],
            'CANCELADA'  => ['label'=>'Cancelada',  'class'=>'badge-danger'],
            default      => ['label'=>$this->estado,'class'=>'badge-secondary'],
        };
    }
}
