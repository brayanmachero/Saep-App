<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KizeoCharlaTracking extends Model
{
    protected $table = 'kizeo_charla_tracking';

    protected $fillable = [
        'kizeo_data_id',
        'kizeo_form_id',
        'asignado_por',
        'asignado_por_id',
        'asignado_a',
        'asignado_a_id',
        'estado',
        'fecha_creacion',
        'fecha_respuesta',
        'semana',
        'anio',
        'metadata',
    ];

    protected $casts = [
        'fecha_creacion'  => 'datetime',
        'fecha_respuesta' => 'datetime',
        'metadata'        => 'array',
    ];

    /* ---- Scopes ---- */

    public function scopeCompletados($q)
    {
        return $q->where('estado', 'completado');
    }

    public function scopePendientes($q)
    {
        return $q->where('estado', 'pendiente');
    }

    public function scopeEnPeriodo($q, string $desde, string $hasta)
    {
        return $q->whereBetween('fecha_creacion', [$desde, $hasta]);
    }

    public function scopeDelAnio($q, int $anio)
    {
        return $q->where('anio', $anio);
    }

    /* ---- Helpers ---- */

    public function getDiasPendienteAttribute(): ?int
    {
        if ($this->estado === 'completado') {
            return null;
        }
        return $this->fecha_creacion
            ? (int) $this->fecha_creacion->diffInDays(now())
            : null;
    }
}
