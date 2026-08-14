<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioVariante extends Model
{
    protected $table = 'inventario_variantes';

    protected $fillable = ['producto_id', 'codigo', 'talla', 'descripcion', 'stock_minimo', 'costo_referencia', 'activo'];

    protected $casts = ['activo' => 'boolean', 'stock_minimo' => 'decimal:3', 'costo_referencia' => 'decimal:2'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(InventarioProducto::class, 'producto_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'variante_id');
    }

    public function historialCostos(): HasMany
    {
        return $this->hasMany(InventarioHistorialCosto::class, 'variante_id')
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id');
    }
}
