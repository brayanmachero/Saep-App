<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parametro extends Model
{
    use SoftDeletes;

    protected $table = 'comercial_parametros';

    protected $fillable = [
        'clave',
        'nombre',
        'descripcion',
        'valor',
        'tipo',
        'editable',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'categoria',
        'version',
        'valor_anterior',
        'actualizado_por',
    ];

    protected $casts = [
        'editable' => 'boolean',
        'fecha_vigencia_desde' => 'date',
        'fecha_vigencia_hasta' => 'date',
    ];

    /**
     * Relación con usuario que actualizó
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'actualizado_por');
    }

    /**
     * Scope: Obtener parámetro vigente por clave
     */
    public static function obtenerVigente($clave)
    {
        return self::where('clave', $clave)
                   ->where(function ($query) {
                       $query->whereNull('fecha_vigencia_desde')
                             ->orWhere('fecha_vigencia_desde', '<=', now());
                   })
                   ->where(function ($query) {
                       $query->whereNull('fecha_vigencia_hasta')
                             ->orWhere('fecha_vigencia_hasta', '>=', now());
                   })
                   ->orderBy('version', 'desc')
                   ->first();
    }

    public static function valor(string $clave, mixed $default = null): mixed
    {
        $parametro = self::obtenerVigente($clave);

        return $parametro ? $parametro->valor_actual : $default;
    }

    /**
     * Scope: Por categoría
     */
    public function scopePorCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Scope: Editables
     */
    public function scopeEditables($query)
    {
        return $query->where('editable', true);
    }

    /**
     * Obtener valor actualizado
     */
    public function getValorActualAttribute()
    {
        return $this->castValue($this->valor, $this->tipo);
    }

    /**
     * Cast valor según tipo
     */
    private function castValue($valor, $tipo)
    {
        return match ($tipo) {
            'integer' => (int) $valor,
            'decimal' => (float) $valor,
            'date' => \Carbon\Carbon::parse($valor),
            'json' => json_decode($valor, true),
            default => $valor,
        };
    }
}
