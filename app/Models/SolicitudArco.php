<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SolicitudArco extends Model
{
    protected $table = 'solicitudes_arco';

    protected $fillable = [
        'numero_solicitud',
        'user_id',
        'canal_origen',
        'titular_nombre',
        'titular_email',
        'titular_rut',
        'titular_telefono',
        'titular_contexto',
        'token_hash',
        'token_expires_at',
        'tipo',
        'descripcion',
        'datos_afectados',
        'causal_invocada',
        'antecedentes',
        'solicita_bloqueo_temporal',
        'bloqueo_temporal_activo',
        'bloqueo_temporal_at',
        'bloqueo_temporal_motivo',
        'estado',
        'respuesta',
        'responsable_id',
        'fecha_solicitud',
        'fecha_respuesta',
        'fecha_vencimiento',
        'motivo_rechazo',
        'estado_ejecucion',
        'resultado_ejecucion',
        'observacion_ejecucion',
        'fecha_ejecucion',
        'ejecutada_por',
        'consentimiento_version',
        'consentimiento_texto',
        'consentimiento_aceptado_at',
        'consentimiento_ip',
        'consentimiento_user_agent',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'fecha_solicitud' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'solicita_bloqueo_temporal' => 'boolean',
        'bloqueo_temporal_activo' => 'boolean',
        'bloqueo_temporal_at' => 'datetime',
        'resultado_ejecucion' => 'array',
        'fecha_ejecucion' => 'datetime',
        'consentimiento_aceptado_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id')->withTrashed();
    }

    public function ejecutor()
    {
        return $this->belongsTo(User::class, 'ejecutada_por')->withTrashed();
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
            'bloqueo' => 'Bloqueo temporal',
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

    public function getTitularNombreMostrarAttribute(): string
    {
        return $this->user?->nombre_completo
            ?? $this->titular_nombre
            ?? 'Titular externo';
    }

    public function getTitularEmailMostrarAttribute(): ?string
    {
        return $this->user?->email ?? $this->titular_email;
    }

    public function getCanalLabelAttribute(): string
    {
        return match ($this->canal_origen) {
            'publico' => 'Público',
            'interno' => 'Interno',
            default => ucfirst((string) $this->canal_origen),
        };
    }

    public function esPublica(): bool
    {
        return $this->canal_origen === 'publico' || $this->user_id === null;
    }

    public function tokenVigente(): bool
    {
        return $this->token_hash !== null
            && ($this->token_expires_at === null || $this->token_expires_at->isFuture());
    }

    public function validarTokenPublico(?string $token): bool
    {
        if (!$token || !$this->tokenVigente()) {
            return false;
        }

        return hash_equals($this->token_hash, hash('sha256', $token));
    }

    public static function generarTokenPublico(): string
    {
        return Str::random(64);
    }
}
