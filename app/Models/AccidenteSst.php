<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccidenteSst extends Model
{
    use SoftDeletes;

    protected $table = 'accidentes_sst';

    protected $fillable = [
        'tipo','trabajador_id','trabajador_nombre','trabajador_rut','trabajador_kizeo_id',
        'trabajador_cargo','centro_costo_id','fecha_hora_accidente','fecha_accidente',
        'hora_accidente','lugar','descripcion','lesiones','causas','medidas_inmediatas',
        'medidas_correctivas','medidas_preventivas','gravedad','dias_perdidos',
        'reportado_mutual','numero_diat','estado','registrado_por',
    ];

    protected $casts = [
        'fecha_hora_accidente' => 'datetime',
        'fecha_accidente'      => 'date',
        'reportado_mutual'     => 'boolean',
        'dias_perdidos'        => 'integer',
    ];

    /* ── Relaciones ────────────────────────────── */

    public function trabajador()    { return $this->belongsTo(User::class, 'trabajador_id'); }
    public function centroCosto()   { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function registradoPor() { return $this->belongsTo(User::class, 'registrado_por'); }

    public function opciones()
    {
        return $this->belongsToMany(OpcionAccidenteSst::class, 'accidente_sst_opcion', 'accidente_sst_id', 'opcion_id')
                    ->withTimestamps();
    }

    public function lesionesOpciones()
    {
        return $this->opciones()->where('tipo', 'lesion');
    }

    public function causasOpciones()
    {
        return $this->opciones()->where('tipo', 'causa');
    }

    public function medidasOpciones()
    {
        return $this->opciones()->where('tipo', 'medida');
    }

    /* ── Atributos computados ─────────────────── */

    public function getGravedadBadgeAttribute(): array
    {
        return match(strtolower($this->gravedad ?? '')) {
            'leve'       => ['label' => 'Leve',       'class' => 'badge-warning'],
            'moderado'   => ['label' => 'Moderado',   'class' => 'badge-warning'],
            'grave'      => ['label' => 'Grave',      'class' => 'badge-danger'],
            'fatal'      => ['label' => 'Fatal',      'class' => 'badge-dark'],
            'sin_lesión','sin_lesion'
                         => ['label' => 'Sin Lesión', 'class' => 'badge-success'],
            default      => ['label' => $this->gravedad ?? '—', 'class' => 'badge-secondary'],
        };
    }

    public function getEstadoBadgeAttribute(): array
    {
        return match(strtolower($this->estado ?? '')) {
            'ingresado'     => ['label' => 'Ingresado',     'class' => 'badge-info'],
            'aceptado'      => ['label' => 'Aceptado',      'class' => 'badge-primary'],
            'rechazado'     => ['label' => 'Rechazado',     'class' => 'badge-danger'],
            'aprobado'      => ['label' => 'Aprobado',      'class' => 'badge-success'],
            'cerrado'       => ['label' => 'Cerrado',       'class' => 'badge-dark'],
            // Legacy
            'notificado'    => ['label' => 'Ingresado',     'class' => 'badge-info'],
            'investigacion' => ['label' => 'En Investigación','class' => 'badge-warning'],
            default         => ['label' => ucfirst($this->estado ?? '—'), 'class' => 'badge-secondary'],
        };
    }
}
