<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioKizeoImputacionHistorial extends Model
{
    protected $table = 'inventario_kizeo_imputacion_historial';

    protected $fillable = [
        'aplicacion_id',
        'centro_costo_anterior_id',
        'centro_costo_anterior',
        'centro_costo_nuevo_id',
        'centro_costo_nuevo',
        'registrado_por',
        'registrado_por_nombre',
    ];

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioEntregaKizeoAplicacion::class, 'aplicacion_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
