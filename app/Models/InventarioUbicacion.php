<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioUbicacion extends Model
{
    public const TIPOS = [
        'BODEGA' => 'Bodega',
        'DESPACHO' => 'Zona de despacho',
        'ESTANTE' => 'Estante o zona interna',
    ];

    protected $table = 'inventario_ubicaciones';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'ubicacion_id');
    }
}
