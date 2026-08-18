<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioKizeoCatalogItem extends Model
{
    protected $table = 'inventario_kizeo_catalog_items';

    protected $fillable = [
        'variante_id', 'kizeo_list_id', 'kizeo_item_id', 'source_hash',
        'sincronizado_en', 'ultimo_error',
    ];

    protected $casts = ['sincronizado_en' => 'datetime'];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(InventarioVariante::class, 'variante_id');
    }
}
