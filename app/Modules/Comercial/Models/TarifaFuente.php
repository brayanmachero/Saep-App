<?php

namespace App\Modules\Comercial\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaFuente extends Model
{
    protected $table = 'comercial_tarifa_fuentes';

    protected $guarded = ['id'];

    public function tarifas()
    {
        return $this->hasMany(TarifaReferencia::class, 'fuente_id');
    }
}
