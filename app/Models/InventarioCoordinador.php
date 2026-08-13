<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioCoordinador extends Model
{
    protected $table = 'inventario_coordinadores';

    protected $fillable = [
        'nombre', 'nombre_normalizado', 'rut', 'cargo', 'correo', 'telefono', 'jefe_operaciones', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function centrosCosto(): HasMany
    {
        return $this->hasMany(InventarioCentroCosto::class, 'coordinador_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'coordinador_id');
    }
}
