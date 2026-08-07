<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    public const TIPOS = [
        'STOCK_INICIAL' => 'Stock inicial',
        'INGRESO_COMPRA' => 'Ingreso por compra',
        'ENTREGA_EPP' => 'Entrega de EPP',
        'DESPACHO_CENTRO' => 'Despacho a centro o region',
        'TRASLADO_SALIDA' => 'Traslado entre ubicaciones',
        'TRASLADO_ENTRADA' => 'Recepcion de traslado',
        'AJUSTE_POSITIVO' => 'Ajuste positivo',
        'AJUSTE_NEGATIVO' => 'Ajuste negativo',
        'REVERSO' => 'Reverso de movimiento',
    ];

    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'codigo', 'tipo', 'origen', 'ubicacion_id', 'producto_id', 'variante_id', 'cantidad',
        'costo_unitario', 'grupo_traslado', 'referencia_tipo', 'referencia_id', 'documento_tipo',
        'documento_numero', 'destinatario_nombre', 'destinatario_rut', 'centro_costo',
        'observacion', 'ocurrido_en', 'registrado_por', 'registrado_por_nombre', 'reverso_de_id',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'costo_unitario' => 'decimal:2',
        'ocurrido_en' => 'datetime',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(InventarioProducto::class, 'producto_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(InventarioVariante::class, 'variante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
