<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modalidad extends Model
{
    use SoftDeletes;

    protected $table = 'comercial_modalidades';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'margen_operacional',
        'sis_porcentaje',
        'mutual_porcentaje',
        'cesantia_porcentaje',
        'factor_vacaciones',
        'refprev_porcentaje',
        'estado',
        'configuracion_adicional',
    ];

    protected $casts = [
        'margen_operacional' => 'decimal:2',
        'sis_porcentaje' => 'decimal:2',
        'mutual_porcentaje' => 'decimal:2',
        'cesantia_porcentaje' => 'decimal:2',
        'factor_vacaciones' => 'decimal:3',
        'refprev_porcentaje' => 'decimal:2',
        'configuracion_adicional' => 'json',
    ];

    /**
     * Relación con cotizaciones
     */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    /**
     * Scope: Activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Obtener por código (EST, SUB)
     */
    public static function porCodigo($codigo)
    {
        return self::where('codigo', $codigo)->firstOrFail();
    }

    /**
     * Es EST?
     */
    public function esEST(): bool
    {
        return $this->codigo === 'EST';
    }

    /**
     * Es SUB?
     */
    public function esSUB(): bool
    {
        return $this->codigo === 'SUB';
    }
}
