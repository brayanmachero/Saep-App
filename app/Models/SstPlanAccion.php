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

    public static function estadosMap(): array
    {
        return [
            'PENDIENTE'   => 'Pendiente',
            'EN_PROGRESO' => 'En Progreso',
            'COMPLETADO'  => 'Completado',
            'CANCELADO'   => 'Cancelado',
        ];
    }

    public function getEstadoBadgeAttribute(): string
    {
        return match($this->estado) {
            'PENDIENTE'   => 'warning',
            'EN_PROGRESO' => 'info',
            'COMPLETADO'  => 'success',
            'CANCELADO'   => 'danger',
            default       => 'secondary',
        };
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::estadosMap()[$this->estado] ?? $this->estado;
    }

    public function getEstaVencidoAttribute(): bool
    {
        return $this->fecha_compromiso
            && $this->fecha_compromiso->isPast()
            && !in_array($this->estado, ['COMPLETADO', 'CANCELADO']);
    }
}
