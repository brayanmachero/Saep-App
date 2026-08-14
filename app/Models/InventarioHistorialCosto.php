<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioHistorialCosto extends Model
{
    protected $table = 'inventario_historial_costos';

    protected $fillable = [
        'variante_id', 'costo_unitario', 'origen', 'referencia_tipo', 'referencia_id',
        'vigente_desde', 'registrado_por',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
        'vigente_desde' => 'datetime',
    ];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(InventarioVariante::class, 'variante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
