<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstActividad extends Model
{
    protected $table = 'sst_actividades';

    protected $fillable = [
        'categoria_id', 'nombre', 'descripcion', 'responsable', 'responsable_id',
        'orden', 'fecha_inicio', 'fecha_fin', 'prioridad', 'estado', 'periodicidad',
        'cantidad_programada',
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

    /**
     * Meses que deben programarse automáticamente según la periodicidad.
     */
    public static function mesesProgramadosPorPeriodicidad(string $periodicidad): array
    {
        return match ($periodicidad) {
            'DIARIA', 'SEMANAL', 'QUINCENAL', 'MENSUAL' => range(1, 12),
            'BIMENSUAL'  => [1, 3, 5, 7, 9, 11],
            'TRIMESTRAL' => [1, 4, 7, 10],
            'SEMESTRAL'  => [1, 7],
            'ANUAL'      => [1],
            default      => [], // UNICA: selección manual
        };
    }

    /**
     * Determina si hoy corresponde enviar un recordatorio según la periodicidad
     * para un mes programado que aún no ha sido realizado.
     */
    public function debeRecordarHoy(int $mesActual): bool
    {
        $seg = $this->seguimiento->firstWhere('mes', $mesActual);
        if (!$seg || !$seg->programado || $seg->realizado) {
            return false;
        }

        $dia = (int) now()->format('j');
        $diaSemana = (int) now()->format('N'); // 1=Lunes ... 7=Domingo

        return match ($this->periodicidad) {
            'DIARIA'     => $diaSemana <= 5,              // Lunes a Viernes
            'SEMANAL'    => $diaSemana === 1,             // Solo Lunes
            'QUINCENAL'  => in_array($dia, [1, 15]),      // 1 y 15 del mes
            'MENSUAL', 'BIMENSUAL', 'TRIMESTRAL',
            'SEMESTRAL', 'ANUAL' => in_array($dia, [1, 15]), // Inicio + seguimiento
            'UNICA'      => in_array($dia, [1]),          // Solo al inicio del mes
            default      => false,
        };
    }

    // Relationships
    public function notificaciones() { return $this->hasMany(SstNotificacionLog::class, 'actividad_id'); }

    // === Relationships ===
    public function categoria()      { return $this->belongsTo(SstCategoria::class, 'categoria_id'); }
    public function responsableUser() { return $this->belongsTo(User::class, 'responsable_id'); }
    public function seguimiento()    { return $this->hasMany(SstSeguimiento::class, 'actividad_id')->orderBy('mes'); }
    public function planesAccion()   { return $this->hasMany(SstPlanAccion::class, 'actividad_id'); }
    public function reprogramaciones() { return $this->hasMany(SstReprogramacion::class, 'actividad_id'); }

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
        $meses = array_fill(1, 12, ['programado' => false, 'realizado' => false, 'observacion' => null, 'cantidad_realizada' => 0]);
        foreach ($this->seguimiento as $s) {
            $meses[$s->mes] = [
                'programado'          => (bool) $s->programado,
                'realizado'           => (bool) $s->realizado,
                'observacion'         => $s->observacion,
                'cantidad_realizada'  => (int) $s->cantidad_realizada,
            ];
        }
        return $meses;
    }
}
