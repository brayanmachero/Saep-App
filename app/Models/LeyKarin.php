<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeyKarin extends Model
{
    protected $table = 'ley_karin';

    protected $fillable = [
        'folio','tipo','denunciante_id','denunciante_nombre','denunciante_rut',
        'denunciado_nombre','denunciado_cargo','centro_costo_id','fecha_denuncia',
        'descripcion_hechos','medidas_cautelares','resultado_investigacion',
        'fecha_resolucion','estado','anonima','investigador_id',
    ];

    protected $casts = [
        'fecha_denuncia'    => 'date',
        'fecha_resolucion'  => 'date',
        'anonima'           => 'boolean',
    ];

    public function denunciante()   { return $this->belongsTo(User::class, 'denunciante_id'); }
    public function investigador()  { return $this->belongsTo(User::class, 'investigador_id'); }
    public function centroCosto()   { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->folio)) {
                $model->folio = 'LK-' . date('Y') . '-' . str_pad(static::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function getEstadoBadgeAttribute(): array
    {
        return match($this->estado) {
            'RECIBIDA'          => ['label'=>'Recibida',           'class'=>'badge-info'],
            'EN_INVESTIGACION'  => ['label'=>'En Investigación',   'class'=>'badge-warning'],
            'RESUELTA'          => ['label'=>'Resuelta',           'class'=>'badge-success'],
            'ARCHIVADA'         => ['label'=>'Archivada',          'class'=>'badge-secondary'],
            default             => ['label'=>$this->estado,        'class'=>'badge-secondary'],
        };
    }
}
