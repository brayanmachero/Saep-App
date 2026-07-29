<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaBodegaItem extends Model
{
    protected $table = 'entrega_bodega_items';

    protected $guarded = ['id'];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaBodega::class, 'entrega_bodega_id');
    }
}
