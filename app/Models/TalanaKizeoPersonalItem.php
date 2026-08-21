<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaKizeoPersonalItem extends Model
{
    protected $table = 'talana_kizeo_personal_items';

    protected $fillable = [
        'rut', 'kizeo_list_id', 'kizeo_item_id', 'source_hash',
        'sincronizado_en', 'ultimo_error',
    ];

    protected $casts = ['sincronizado_en' => 'datetime'];
}
