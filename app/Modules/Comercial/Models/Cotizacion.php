<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cotizacion extends Model
{
    use SoftDeletes;

    protected $table = 'comercial_cotizaciones';

    protected $fillable = [
        'numero',
        'titulo',
        'cargo',
        'cliente_id',
        'centro_costo_id',
        'modalidad_id',
        'usuario_id',
        'estado',
        'version',
        'cotizacion_anterior_id',
        'fecha_cotizacion',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'observaciones',
        'total_remuneraciones',
        'total_cotizaciones',
        'total_provisiones',
        'total_gastos',
        'subtotal',
        'margen',
        'precio_venta',
        'datos_calculo',
        'detalles_json',
        'fecha_aprobacion',
        'fecha_vigencia',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'total_remuneraciones' => 'decimal:2',
        'total_cotizaciones' => 'decimal:2',
        'total_provisiones' => 'decimal:2',
        'total_gastos' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'margen' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'datos_calculo' => 'json',
        'detalles_json' => 'json',
        'fecha_cotizacion' => 'date',
        'fecha_vigencia_desde' => 'date',
        'fecha_vigencia_hasta' => 'date',
        'fecha_aprobacion' => 'datetime',
        'fecha_vigencia' => 'datetime',
        'fecha_cancelacion' => 'datetime',
    ];

    /**
     * Relación con cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con centro de costo
     */
    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }

    /**
     * Relación con modalidad
     */
    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class);
    }

    /**
     * Relación con usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relación con cotización anterior
     */
    public function cotizacionAnterior(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_anterior_id');
    }

    /**
     * Relación con cotizaciones posteriores
     */
    public function cotizacionesPosteriores(): HasMany
    {
        return $this->hasMany(Cotizacion::class, 'cotizacion_anterior_id');
    }

    /**
     * Relación con detalles
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    /**
     * Relación con uniformes
     */
    public function uniformes(): HasMany
    {
        return $this->hasMany(CotizacionUniforme::class);
    }

    /**
     * Relación con auditoría
     */
    public function auditorias(): HasMany
    {
        return $this->hasMany(CotizacionAuditoria::class);
    }

    /**
     * Scope: Por estado
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope: Por cliente
     */
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    /**
     * Scope: Vigentes
     */
    public function scopeVigentes($query)
    {
        return $query->where('estado', 'vigente')
                     ->where(function ($subQuery) {
                         $subQuery->whereNull('fecha_vigencia_hasta')
                             ->orWhere('fecha_vigencia_hasta', '>=', now());
                     });
    }

    /**
     * Scope: Por rango de fechas
     */
    public function scopePorFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_cotizacion', [$desde, $hasta]);
    }

    /**
     * Generar número de cotización automático
     */
    public static function generarNumero()
    {
        $year = now()->format('Y');
        $prefix = "COTIZ-{$year}-";
        $ultimoNumero = self::where('numero', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('numero')
            ->value('numero');

        $contador = $ultimoNumero
            ? ((int) substr($ultimoNumero, -5)) + 1
            : 1;

        return $prefix . str_pad((string) $contador, 5, '0', STR_PAD_LEFT);
    }

    /**
     * ¿Es aprovable?
     */
    public function esAprovable(): bool
    {
        return $this->estado === 'en_cotizacion';
    }

    /**
     * ¿Es vigente?
     */
    public function esVigente(): bool
    {
        return $this->estado === 'vigente'
            && ($this->fecha_vigencia_hasta === null || $this->fecha_vigencia_hasta >= now());
    }
}
