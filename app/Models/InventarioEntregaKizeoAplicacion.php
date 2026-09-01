<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioEntregaKizeoAplicacion extends Model
{
    public const ESTADOS = [
        'APLICADA' => 'Aplicada a stock',
        'CORREGIDA' => 'Corregida automáticamente',
        'REVERSADA' => 'Reversada',
    ];

    protected $table = 'inventario_entrega_kizeo_aplicaciones';

    protected $fillable = [
        'entrega_bodega_id', 'ubicacion_id', 'estado', 'fuente_actualizada_en',
        'aplicada_por', 'aplicada_en', 'corregida_en', 'fuente_corregida_en',
        'revertida_por', 'revertida_en', 'motivo_reversion', 'correccion_snapshot',
        'correccion_pendiente_motivo', 'observacion',
    ];

    protected $casts = [
        'fuente_actualizada_en' => 'datetime',
        'aplicada_en' => 'datetime',
        'corregida_en' => 'datetime',
        'fuente_corregida_en' => 'datetime',
        'correccion_snapshot' => 'array',
        'revertida_en' => 'datetime',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaBodega::class, 'entrega_bodega_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(InventarioEntregaKizeoLinea::class, 'aplicacion_id')->orderBy('linea_fuente');
    }

    /** Movimientos que dejan constancia del ajuste automático posterior de Kizeo. */
    public function movimientosCorreccion(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'referencia_id')
            ->where('referencia_tipo', self::class)
            ->where('origen', 'CORRECCION_KIZEO_EPP')
            ->orderBy('id');
    }

    public function aplicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicada_por');
    }

    public function revertidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertida_por');
    }
}
