<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratacionSyncLog extends Model
{
    protected $table = 'contratacion_sync_log';

    public const STATUS_EN_PROCESO = 'en_proceso';
    public const STATUS_EXITOSO    = 'exitoso';
    public const STATUS_FALLIDO    = 'fallido';

    public const ACCION_SUBIDA_FICHA = 'subida_ficha';
    public const ACCION_RESINCRONIZACION = 'resincronizacion';

    protected $fillable = [
        'postulante_id',
        'accion',
        'status',
        'intento',
        'archivo_nombre',
        'archivo_tamano',
        'sharepoint_site',
        'sharepoint_path',
        'sharepoint_item_id',
        'origen',
        'error_mensaje',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(PostulanteContratacion::class, 'postulante_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_EXITOSO    => 'Exitoso',
            self::STATUS_FALLIDO    => 'Fallido',
            self::STATUS_EN_PROCESO => 'En proceso',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_EXITOSO    => '#16a34a',
            self::STATUS_FALLIDO    => '#dc2626',
            self::STATUS_EN_PROCESO => '#f59e0b',
            default => '#64748b',
        };
    }

    public function getDuracionSegundosAttribute(): ?float
    {
        if (!$this->started_at || !$this->finished_at) return null;
        return round($this->finished_at->getTimestamp() - $this->started_at->getTimestamp() + ($this->finished_at->micro - $this->started_at->micro) / 1e6, 2);
    }
}
