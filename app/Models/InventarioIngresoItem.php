<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioIngresoItem extends Model
{
    protected $table = 'inventario_ingreso_items';

    protected $fillable = ['ingreso_id', 'producto_id', 'variante_id', 'cantidad', 'costo_unitario'];

    protected $casts = ['cantidad' => 'decimal:3', 'costo_unitario' => 'decimal:2'];

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(InventarioIngreso::class, 'ingreso_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(InventarioProducto::class, 'producto_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(InventarioVariante::class, 'variante_id');
    }
}
