<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One record per logical row imported from the manual movements template.
 *
 * It prevents a spreadsheet from being applied twice while keeping the actual
 * inventory movement fully reversible through the existing Kardex workflow.
 */
class InventarioImportacionMovimiento extends Model
{
    protected $table = 'inventario_importacion_movimientos';

    protected $fillable = [
        'referencia',
        'movimiento_id',
        'registrado_por',
    ];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(InventarioMovimiento::class, 'movimiento_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
