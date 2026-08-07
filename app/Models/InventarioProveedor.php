<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioProveedor extends Model
{
    protected $table = 'inventario_proveedores';

    protected $fillable = ['nombre', 'rut', 'contacto', 'email', 'telefono', 'observacion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function ingresos(): HasMany
    {
        return $this->hasMany(InventarioIngreso::class, 'proveedor_id');
    }
}
