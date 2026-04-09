<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charla extends Model
{
    use SoftDeletes;

    protected $table = 'charlas';

    protected $fillable = [
        'titulo', 'contenido', 'tipo', 'lugar', 'fecha_programada',
        'duracion_minutos', 'creado_por', 'supervisor_id', 'centro_costo_id',
        'estado', 'archivos_adjuntos', 'activo', 'fecha_dictado',
    ];

    protected $casts = [
        'fecha_programada'   => 'datetime',
        'fecha_dictado'      => 'datetime',
        'archivos_adjuntos'  => 'array',
        'activo'             => 'boolean',
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function asistentes()
    {
        return $this->hasMany(CharlaAsistente::class, 'charla_id');
    }

    public function relatores()
    {
        return $this->hasMany(CharlaRelator::class, 'charla_id');
    }

    public function getEstadoBadgeAttribute(): array
    {
        return match ($this->estado) {
            'BORRADOR'   => ['class' => 'badge-secondary',  'label' => 'Borrador'],
            'PROGRAMADA' => ['class' => 'badge-warning',    'label' => 'Programada'],
            'EN_CURSO'   => ['class' => 'badge-info',       'label' => 'En Curso'],
            'COMPLETADA' => ['class' => 'badge-success',    'label' => 'Completada'],
            'CANCELADA'  => ['class' => 'badge-danger',     'label' => 'Cancelada'],
            default      => ['class' => 'badge-secondary',  'label' => $this->estado],
        };
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'CHARLA_5MIN'    => 'Charla 5 Min',
            'CAPACITACION'   => 'Capacitación',
            'INDUCCION'      => 'Inducción',
            'CHARLA_ESPECIAL' => 'Charla Especial',
            default          => $this->tipo,
        };
    }

    public function getFirmaProgressAttribute(): array
    {
        $total    = $this->asistentes_count ?? $this->asistentes->count();
        $firmados = $this->asistentes->where('estado', 'FIRMADO')->count();
        return [
            'total'    => $total,
            'firmados' => $firmados,
            'percent'  => $total > 0 ? round($firmados / $total * 100) : 0,
        ];
    }
}
