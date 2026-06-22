<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionAuditoria extends Model
{
    public $timestamps = true;
    public $updated_at = null;

    protected $table = 'comercial_cotizacion_auditorias';

    protected $fillable = [
        'cotizacion_id',
        'usuario_id',
        'accion',
        'descripcion',
        'cambios',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'cambios' => 'json',
    ];

    /**
     * Relación con cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Relación con usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Scope: Por acción
     */
    public function scopePorAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    /**
     * Scope: Por cotización
     */
    public function scopePorCotizacion($query, $cotizacionId)
    {
        return $query->where('cotizacion_id', $cotizacionId)
                     ->orderBy('created_at', 'desc');
    }
}
