<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaRevision extends Model
{
    protected $table = 'comercial_tarifa_revisiones';

    protected $guarded = ['id'];

    protected $casts = ['antes' => 'array', 'despues' => 'array'];
}
