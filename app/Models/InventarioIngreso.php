<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioIngreso extends Model
{
    public const TIPOS_DOCUMENTO = [
        'FACTURA' => 'Factura',
        'GUIA_DESPACHO' => 'Guia de despacho',
        'OTRO' => 'Otro respaldo',
    ];

    protected $table = 'inventario_ingresos';

    protected $fillable = [
        'codigo', 'ubicacion_id', 'proveedor_id', 'tipo_documento', 'numero_documento',
        'fecha_documento', 'fecha_recepcion', 'observacion', 'registrado_por',
        'reversado_por', 'reversado_en', 'motivo_reversion',
    ];

    protected $casts = [
        'fecha_documento' => 'date',
        'fecha_recepcion' => 'date',
        'reversado_en' => 'datetime',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(InventarioProveedor::class, 'proveedor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventarioIngresoItem::class, 'ingreso_id');
    }

    public function reversadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversado_por');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
