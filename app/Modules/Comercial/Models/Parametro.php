<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function auditorias(): HasMany
    {
        return $this->hasMany(ParametroAuditoria::class);
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

    public function getFormatoVisualAttribute(): string
    {
        $clave = strtoupper($this->clave);
        $categoria = strtoupper((string) $this->categoria);

        if (
            $categoria === 'MARGENES'
            || str_starts_with($categoria, 'TASAS')
            || in_array($clave, [
                'IPC',
                'IMPOSICIONES_PORCENTAJE',
                'IMPUESTO_UNICO_FACTOR',
                'GASTOS_ADMIN_EST',
                'GASTOS_ADMIN_SUB',
            ], true)
        ) {
            return 'porcentaje';
        }

        if (
            str_starts_with($clave, 'UNIFORME_')
            || in_array($clave, [
                'UF',
                'SUELDO_MINIMO',
                'GRATIFICACION_TOPE',
                'IMPUESTO_UNICO_REBAJA',
                'AGUINALDO_EST',
                'AGUINALDO_SUB',
            ], true)
        ) {
            return 'moneda';
        }

        if ($this->tipo === 'integer' || str_starts_with($clave, 'HORAS_') || str_contains($clave, 'MESES') || str_contains($clave, 'DIAS')) {
            return 'entero';
        }

        return 'decimal';
    }

    public function getUnidadVisualAttribute(): string
    {
        if (strtoupper($this->clave) === 'UF') {
            return 'UF';
        }

        return match ($this->formato_visual) {
            'moneda' => '$',
            'porcentaje' => '%',
            'entero' => 'entero',
            default => 'decimal',
        };
    }

    public function formatearValorVisual(mixed $valor = null): string
    {
        $valor = $valor ?? $this->valor;
        if ($valor === null || $valor === '') {
            return '';
        }

        $texto = trim((string) $valor);
        $texto = preg_replace('/[^\d,.\-]/', '', $texto) ?? '';
        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (in_array($this->formato_visual, ['moneda', 'entero'], true)) {
            $partes = explode('.', $texto);
            if (count($partes) > 2 || (count($partes) === 2 && strlen(end($partes)) === 3)) {
                $texto = str_replace('.', '', $texto);
            }
        }

        $numero = is_numeric($texto) ? (float) $texto : 0.0;

        return match ($this->formato_visual) {
            'moneda' => number_format($numero, floor($numero) == $numero ? 0 : 2, ',', '.'),
            'porcentaje' => number_format($numero, floor($numero) == $numero ? 0 : 2, ',', '.'),
            'entero' => number_format((int) round($numero), 0, ',', '.'),
            default => number_format($numero, ($numero > 0 && $numero < 1) ? 6 : (floor($numero) == $numero ? 0 : 2), ',', '.'),
        };
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
