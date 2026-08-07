<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioConteoLinea extends Model
{
    protected $table = 'inventario_conteo_lineas';

    protected $fillable = ['conteo_id', 'producto_id', 'variante_id', 'cantidad_sistema', 'cantidad_fisica', 'observacion'];

    protected $casts = ['cantidad_sistema' => 'decimal:3', 'cantidad_fisica' => 'decimal:3'];

    public function conteo(): BelongsTo
    {
        return $this->belongsTo(InventarioConteo::class, 'conteo_id');
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
