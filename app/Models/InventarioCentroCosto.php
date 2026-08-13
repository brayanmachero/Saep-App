<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioCentroCosto extends Model
{
    protected $table = 'inventario_centros_costo';

    protected $fillable = [
        'numero_maestro', 'nombre', 'nombre_normalizado', 'tipo', 'comuna', 'direccion', 'jefe_operaciones',
        'coordinador_id', 'coordinador_nombre_origen', 'cargo_contacto', 'correo_contacto', 'telefono_contacto', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(InventarioCoordinador::class, 'coordinador_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'centro_costo_id');
    }
}
