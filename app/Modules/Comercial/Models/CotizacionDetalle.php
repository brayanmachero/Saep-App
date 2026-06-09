<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CotizacionDetalle extends Model
{
    use SoftDeletes;

    protected $table = 'comercial_cotizacion_detalles';

    protected $fillable = [
        'cotizacion_id',
        'tipo',
        'concepto',
        'descripcion',
        'valor_base',
        'porcentaje',
        'valor',
        'formula',
        'calculos_paso_a_paso',
        'orden',
    ];

    protected $casts = [
        'valor_base' => 'decimal:2',
        'porcentaje' => 'decimal:2',
        'valor' => 'decimal:2',
        'formula' => 'json',
        'calculos_paso_a_paso' => 'json',
    ];

    /**
     * Relación con cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Scope: Por tipo
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope: Ordenados
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden', 'asc');
    }

    /**
     * Tipos válidos
     */
    public static function tiposValidos(): array
    {
        return ['remuneracion', 'cotizacion', 'provision', 'gasto', 'uniforme', 'margen'];
    }
}
