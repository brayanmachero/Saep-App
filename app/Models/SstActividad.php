<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstActividad extends Model
{
    protected $table = 'sst_actividades';

    protected $fillable = [
        'categoria_id', 'nombre', 'descripcion', 'responsable', 'responsable_id',
        'orden', 'fecha_inicio', 'fecha_fin', 'prioridad', 'estado', 'periodicidad',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public static function prioridadesMap(): array
    {
        return [
            'ALTA'  => 'Alta',
            'MEDIA' => 'Media',
            'BAJA'  => 'Baja',
        ];
    }

    public static function estadosMap(): array
    {
        return [
            'PENDIENTE'   => 'Pendiente',
            'EN_PROGRESO' => 'En Progreso',
            'COMPLETADA'  => 'Completada',
            'CANCELADA'   => 'Cancelada',
        ];
    }

    public static function periodicidadesMap(): array
    {
        return [
            'UNICA'      => 'Única',
            'DIARIA'     => 'Diaria',
            'SEMANAL'    => 'Semanal',
            'QUINCENAL'  => 'Quincenal',
            'MENSUAL'    => 'Mensual',
            'BIMENSUAL'  => 'Bimensual',
            'TRIMESTRAL' => 'Trimestral',
            'SEMESTRAL'  => 'Semestral',
            'ANUAL'      => 'Anual',
        ];
    }

    // === Relationships ===
    public function categoria()      { return $this->belongsTo(SstCategoria::class, 'categoria_id'); }
    public function responsableUser() { return $this->belongsTo(User::class, 'responsable_id'); }
    public function seguimiento()    { return $this->hasMany(SstSeguimiento::class, 'actividad_id')->orderBy('mes'); }
    public function planesAccion()   { return $this->hasMany(SstPlanAccion::class, 'actividad_id'); }

    // === Accessors ===
    public function getNombreResponsableAttribute(): string
    {
        return $this->responsableUser?->nombre_completo ?? $this->responsable ?? '—';
    }

    public function getEstadoBadgeAttribute(): string
    {
        return match($this->estado) {
            'PENDIENTE'   => 'warning',
            'EN_PROGRESO' => 'info',
            'COMPLETADA'  => 'success',
            'CANCELADA'   => 'danger',
            default       => 'secondary',
        };
    }

    public function getPrioridadBadgeAttribute(): string
    {
        return match($this->prioridad) {
            'ALTA'  => 'danger',
            'MEDIA' => 'warning',
            'BAJA'  => 'info',
            default => 'secondary',
        };
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->fecha_fin
            && $this->fecha_fin->isPast()
            && !in_array($this->estado, ['COMPLETADA', 'CANCELADA']);
    }

    public function getEstaPorVencerAttribute(): bool
    {
        return $this->fecha_fin
            && $this->fecha_fin->isFuture()
            && $this->fecha_fin->diffInDays(now()) <= 7
            && !in_array($this->estado, ['COMPLETADA', 'CANCELADA']);
    }

    public function getSeguimientoPorMesAttribute(): array
    {
        $meses = array_fill(1, 12, ['programado' => false, 'realizado' => false, 'observacion' => null]);
        foreach ($this->seguimiento as $s) {
            $meses[$s->mes] = [
                'programado'  => (bool) $s->programado,
                'realizado'   => (bool) $s->realizado,
                'observacion' => $s->observacion,
            ];
        }
        return $meses;
    }
}
