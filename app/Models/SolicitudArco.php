<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudArco extends Model
{
    protected $table = 'solicitudes_arco';

    protected $fillable = [
        'numero_solicitud',
        'user_id',
        'tipo',
        'descripcion',
        'datos_afectados',
        'estado',
        'respuesta',
        'responsable_id',
        'fecha_solicitud',
        'fecha_respuesta',
        'fecha_vencimiento',
        'motivo_rechazo',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_vencimiento' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public static function generarNumero(): string
    {
        $year = now()->year;
        $ultimo = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('ARCO-%d-%04d', $year, $ultimo);
    }

    public function getNombreTipoAttribute(): string
    {
        return match ($this->tipo) {
            'acceso' => 'Acceso a datos',
            'rectificacion' => 'Rectificación',
            'supresion' => 'Supresión / Cancelación',
            'oposicion' => 'Oposición al tratamiento',
            'portabilidad' => 'Portabilidad de datos',
            default => $this->tipo,
        };
    }

    public function getNombreEstadoAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => 'Pendiente',
            'en_revision' => 'En Revisión',
            'aprobada' => 'Aprobada',
            'rechazada' => 'Rechazada',
            'completada' => 'Completada',
            default => $this->estado,
        };
    }

    public function getColorEstadoAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => '#f59e0b',
            'en_revision' => '#3b82f6',
            'aprobada' => '#10b981',
            'rechazada' => '#ef4444',
            'completada' => '#6b7280',
            default => '#6b7280',
        };
    }
}
