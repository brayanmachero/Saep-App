<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioEntregaKizeoLinea extends Model
{
    protected $table = 'inventario_entrega_kizeo_lineas';

    protected $fillable = [
        'aplicacion_id', 'linea_fuente', 'articulo_fuente', 'talla_fuente',
        'cantidad_fuente', 'producto_id', 'variante_id', 'movimiento_id',
        'reverso_movimiento_id',
    ];

    protected $casts = ['cantidad_fuente' => 'decimal:3'];

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioEntregaKizeoAplicacion::class, 'aplicacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(InventarioProducto::class, 'producto_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(InventarioVariante::class, 'variante_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(InventarioMovimiento::class, 'movimiento_id');
    }

    public function reversoMovimiento(): BelongsTo
    {
        return $this->belongsTo(InventarioMovimiento::class, 'reverso_movimiento_id');
    }
}
