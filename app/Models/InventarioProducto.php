<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioProducto extends Model
{
    protected $table = 'inventario_productos';

    protected $fillable = [
        'codigo', 'nombre', 'tipo', 'categoria', 'subcategoria', 'unidad_medida',
        'stock_minimo', 'activo', 'creado_por',
    ];

    protected $casts = ['activo' => 'boolean', 'stock_minimo' => 'decimal:3'];

    public function variantes(): HasMany
    {
        return $this->hasMany(InventarioVariante::class, 'producto_id')->orderBy('talla');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
