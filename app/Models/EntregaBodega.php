<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EntregaBodega extends Model
{
    protected $table = 'entregas_bodega';

    protected $guarded = ['id'];

    protected $casts = [
        'descuento_intentado_en' => 'datetime',
        'fecha_pedido' => 'date',
        'kizeo_created_at' => 'datetime',
        'kizeo_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'fuente_ausente_desde' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(EntregaBodegaItem::class, 'entrega_bodega_id')->orderBy('linea');
    }

    public function motivoDescuentoPendiente(): string
    {
        return $this->descuento_error ?: 'No hay un motivo de fallo registrado para esta entrega. Revisar su fecha, estado de fuente y configuración del descuento automático; no se debe asumir que faltó stock.';
    }

    public function accionDescuentoPendiente(): string
    {
        if (str_contains((string) $this->descuento_error, 'Stock insuficiente')) {
            return 'Verificar artículo, talla y existencias en la ubicación de salida. Registrar únicamente ingresos reales pendientes y luego revisar/aplicar la entrega. El saldo indicado corresponde al intento, no al stock actual.';
        }
        if (str_contains((string) $this->descuento_error, 'catálogo')) {
            return 'Revisar la correspondencia de artículo y talla con el catálogo antes de aplicar la entrega.';
        }
        return 'Revisar el comprobante y sus líneas antes de aplicar. Si el problema es técnico, contactar al administrador.';
    }

    public function inventarioAplicacion(): HasOne
    {
        return $this->hasOne(InventarioEntregaKizeoAplicacion::class, 'entrega_bodega_id');
    }
}
