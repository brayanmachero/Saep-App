<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaAssignationSummary extends Model
{
    protected $table = 'talana_assignation_summaries';

    protected $fillable = [
        'talana_id',
        'persona_talana_id',
        'persona_rut',
        'persona_nombre',
        'fecha',
        'working_day',
        'absence_type',
        'status',
        'entrance_datetime',
        'exit_datetime',
        'working_seconds',
        'delay_seconds',
        'synced_at',
    ];

    protected $casts = [
        'fecha'            => 'date',
        'working_day'      => 'boolean',
        'entrance_datetime'=> 'datetime',
        'exit_datetime'    => 'datetime',
        'synced_at'        => 'datetime',
    ];

    public function persona()
    {
        return $this->belongsTo(TalanaPersona::class, 'persona_talana_id', 'talana_id');
    }

    /**
     * Devuelve un mapa persona_talana_id → working_day (bool) para una fecha dada.
     * Útil en el reporte de asistencia para distinguir descansos de ausencias reales.
     */
    public static function mapaJornadaPorFecha(string $fecha): array
    {
        return static::where('fecha', $fecha)
            ->select('persona_talana_id', 'working_day')
            ->get()
            ->pluck('working_day', 'persona_talana_id')
            ->map(fn($v) => (bool) $v)
            ->toArray();
    }

    /**
     * Devuelve un mapa persona_talana_id → working_seconds (int) para una fecha dada.
     * Útil para calcular horas trabajadas según el dato calculado por Talana.
     * Solo incluye registros donde working_seconds no es null.
     */
    public static function mapaSegundosTrabajadasPorFecha(string $fecha): array
    {
        return static::where('fecha', $fecha)
            ->whereNotNull('working_seconds')
            ->select('persona_talana_id', 'working_seconds')
            ->get()
            ->pluck('working_seconds', 'persona_talana_id')
            ->toArray();
    }
}
