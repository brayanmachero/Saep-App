<?php

namespace App\Models;

use Carbon\Carbon;
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
        if ($this->usaSeguimientoPorOcurrencia()) {
            return $this->ocurrencias()
                ->where('programado', true)
                ->where('realizado', false)
                ->whereDate('fecha_programada', now()->toDateString())
                ->exists();
        }

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
    public function ocurrencias()    { return $this->hasMany(SstSeguimientoOcurrencia::class, 'actividad_id')->orderBy('fecha_programada'); }
    public function planesAccion()   { return $this->hasMany(SstPlanAccion::class, 'actividad_id'); }
    public function reprogramaciones() { return $this->hasMany(SstReprogramacion::class, 'actividad_id'); }
    public function comentarios() { return $this->hasMany(SstActividadComentario::class, 'actividad_id')->latest(); }
    public function logs() { return $this->hasMany(SstActividadLog::class, 'actividad_id')->latest(); }

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

    public function usaSeguimientoPorOcurrencia(): bool
    {
        return in_array($this->periodicidad, ['DIARIA', 'SEMANAL'], true);
    }

    /**
     * Crea o reactiva las ocurrencias que corresponden a una actividad diaria o semanal.
     * Las ocurrencias fuera del rango nuevo se preservan como histórico, pero dejan de contar.
     */
    public function sincronizarOcurrencias(): void
    {
        if (!$this->usaSeguimientoPorOcurrencia()) {
            return;
        }

        $this->loadMissing('categoria.programa');
        $anio = (int) ($this->categoria?->programa?->anio ?? now()->year);
        $inicio = $this->fecha_inicio?->copy()->startOfDay() ?? Carbon::create($anio, 1, 1)->startOfDay();
        $fin = $this->fecha_fin?->copy()->startOfDay() ?? Carbon::create($anio, 12, 31)->startOfDay();

        if ($fin->lt($inicio)) {
            return;
        }

        $this->ocurrencias()->where('programado', true)->update(['programado' => false]);

        foreach ($this->generarOcurrencias($inicio, $fin) as $ocurrencia) {
            $this->ocurrencias()->updateOrCreate(
                [
                    'tipo' => $this->periodicidad,
                    'fecha_programada' => $ocurrencia['fecha_programada']->toDateString(),
                ],
                [
                    'fecha_inicio' => $ocurrencia['fecha_inicio']->toDateString(),
                    'fecha_fin' => $ocurrencia['fecha_fin']->toDateString(),
                    'programado' => true,
                ]
            );
        }

        $this->unsetRelation('ocurrencias');
        $this->sincronizarResumenDesdeOcurrencias();
    }

    public function sincronizarResumenDesdeOcurrencias(?int $soloMes = null, ?int $usuarioId = null): void
    {
        if (!$this->usaSeguimientoPorOcurrencia()) {
            return;
        }

        $query = $this->ocurrencias()->where('programado', true);
        if ($soloMes) {
            $query->whereMonth('fecha_programada', $soloMes);
        }

        $query->get()->groupBy(fn (SstSeguimientoOcurrencia $o) => $o->fecha_programada->month)
            ->each(function ($ocurrencias, $mes) use ($usuarioId) {
                $programadas = $ocurrencias->count();
                $realizadas = $ocurrencias->where('realizado', true)->count();

                $this->seguimiento()->updateOrCreate(
                    ['mes' => (int) $mes],
                    [
                        'programado' => $programadas > 0,
                        'realizado' => $programadas > 0 && $realizadas >= $programadas,
                        'cantidad_realizada' => $realizadas,
                        'actualizado_por' => $usuarioId,
                        'fecha_actualizacion' => now(),
                    ]
                );
            });
    }

    public function getSeguimientoPorMesAttribute(): array
    {
        $cantidadEstandar = max(1, (int) ($this->cantidad_programada ?? 1));
        $meses = array_fill(1, 12, [
            'programado' => false,
            'realizado' => false,
            'observacion' => null,
            'cantidad_realizada' => 0,
            'cantidad_programada' => $cantidadEstandar,
        ]);
        foreach ($this->seguimiento as $s) {
            $meses[$s->mes] = [
                'programado'          => (bool) $s->programado,
                'realizado'           => (bool) $s->realizado,
                'observacion'         => $s->observacion,
                'cantidad_realizada'  => (int) $s->cantidad_realizada,
                'cantidad_programada' => $cantidadEstandar,
            ];
        }

        if ($this->usaSeguimientoPorOcurrencia()) {
            $ocurrencias = $this->relationLoaded('ocurrencias')
                ? $this->ocurrencias
                : $this->ocurrencias()->where('programado', true)->get();

            $ocurrencias->where('programado', true)
                ->groupBy(fn (SstSeguimientoOcurrencia $o) => $o->fecha_programada->month)
                ->each(function ($items, $mes) use (&$meses) {
                    $programadas = $items->count();
                    $realizadas = $items->where('realizado', true)->count();
                    $meses[(int) $mes] = [
                        'programado' => $programadas > 0,
                        'realizado' => $programadas > 0 && $realizadas >= $programadas,
                        'observacion' => null,
                        'cantidad_realizada' => $realizadas,
                        'cantidad_programada' => $programadas,
                    ];
                });
        }

        return $meses;
    }

    /** @return array<int, array{fecha_programada: Carbon, fecha_inicio: Carbon, fecha_fin: Carbon}> */
    private function generarOcurrencias(Carbon $inicio, Carbon $fin): array
    {
        $ocurrencias = [];

        if ($this->periodicidad === 'DIARIA') {
            for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
                $ocurrencias[] = [
                    'fecha_programada' => $fecha->copy(),
                    'fecha_inicio' => $fecha->copy(),
                    'fecha_fin' => $fecha->copy(),
                ];
            }

            return $ocurrencias;
        }

        for ($periodoInicio = $inicio->copy(); $periodoInicio->lte($fin);) {
            $periodoFin = $periodoInicio->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
            if ($periodoFin->gt($fin)) {
                $periodoFin = $fin->copy();
            }

            $ocurrencias[] = [
                'fecha_programada' => $periodoInicio->copy(),
                'fecha_inicio' => $periodoInicio->copy(),
                'fecha_fin' => $periodoFin->copy(),
            ];

            $periodoInicio = $periodoFin->copy()->addDay();
        }

        return $ocurrencias;
    }
}
