<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntregaBodega extends Model
{
    protected $table = 'entregas_bodega';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_pedido' => 'date',
        'kizeo_created_at' => 'datetime',
        'kizeo_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(EntregaBodegaItem::class, 'entrega_bodega_id')->orderBy('linea');
    }
}
