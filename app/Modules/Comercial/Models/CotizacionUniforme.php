<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CotizacionUniforme extends Model
{
    use SoftDeletes;

    protected $table = 'comercial_cotizacion_uniformes';

    protected $fillable = [
        'cotizacion_id',
        'descripcion',
        'especificaciones',
        'cantidad',
        'precio_unitario',
        'total',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Relación con cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Calcular total al guardar
     */
    protected static function booted()
    {
        static::saving(function ($model) {
            $model->total = $model->cantidad * $model->precio_unitario;
        });
    }
}
