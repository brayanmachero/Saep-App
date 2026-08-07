<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioVariante extends Model
{
    protected $table = 'inventario_variantes';

    protected $fillable = ['producto_id', 'codigo', 'talla', 'descripcion', 'stock_minimo', 'activo'];

    protected $casts = ['activo' => 'boolean', 'stock_minimo' => 'decimal:3'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(InventarioProducto::class, 'producto_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'variante_id');
    }
}
