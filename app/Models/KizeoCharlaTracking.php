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
        'titulo_actividad',
        'lugar',
        'estado',
        'estatus_kizeo',
        'fecha_creacion',
        'fecha_asignacion',
        'fecha_respuesta',
        'origin_answer',
        'direction',
        'semana',
        'anio',
        'metadata',
    ];

    protected $casts = [
        'fecha_creacion'   => 'datetime',
        'fecha_asignacion' => 'datetime',
        'fecha_respuesta'  => 'datetime',
        'metadata'         => 'array',
    ];

    /* ---- Scopes ---- */

    public function scopeCompletados($q)
    {
        return $q->where('estado', 'completado');
    }

    public function scopePendientes($q)
    {
        return $q->whereIn('estado', ['pendiente', 'transferido']);
    }

    public function scopeTransferidos($q)
    {
        return $q->where('estado', 'transferido');
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
        $ref = $this->fecha_asignacion ?? $this->fecha_creacion;
        return $ref ? (int) $ref->diffInDays(now()) : null;
    }

    public function getEstatusLabelAttribute(): string
    {
        return match ($this->estatus_kizeo) {
            'registrado'  => 'Registrado',
            'transferido' => 'Transferido',
            'recuperado'  => 'Recuperado',
            'terminado'   => 'Terminado',
            default       => ucfirst($this->estado),
        };
    }

    public function getEstatusColorAttribute(): string
    {
        return match ($this->estatus_kizeo) {
            'registrado', 'terminado' => '#22c55e',
            'transferido'             => '#f97316',
            'recuperado'              => '#3b82f6',
            default                   => '#6b7280',
        };
    }
}
