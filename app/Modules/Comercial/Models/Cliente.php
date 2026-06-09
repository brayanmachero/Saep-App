<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'comercial_clientes';

    protected $fillable = [
        'rut',
        'nombre',
        'nombre_comercial',
        'email',
        'telefono',
        'direccion',
        'ciudad',
        'region',
        'contacto_principal',
        'contacto_email',
        'contacto_telefono',
        'estado',
        'datos_adicionales',
    ];

    protected $casts = [
        'datos_adicionales' => 'json',
    ];

    /**
     * Relación con centros de costo
     */
    public function centrosCosto(): HasMany
    {
        return $this->hasMany(CentroCosto::class);
    }

    /**
     * Relación con cotizaciones
     */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    /**
     * Scope: Clientes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope: Buscar por RUT o nombre
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where('rut', 'like', "%{$termino}%")
                     ->orWhere('nombre', 'like', "%{$termino}%")
                     ->orWhere('nombre_comercial', 'like', "%{$termino}%");
    }
}
