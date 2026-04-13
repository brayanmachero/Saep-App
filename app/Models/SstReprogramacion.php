<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstReprogramacion extends Model
{
    protected $table = 'sst_reprogramaciones';

    protected $fillable = [
        'actividad_id', 'mes_original', 'mes_nuevo', 'motivo', 'reprogramado_por',
    ];

    protected $casts = [
        'mes_original' => 'integer',
        'mes_nuevo'    => 'integer',
    ];

    public function actividad()  { return $this->belongsTo(SstActividad::class, 'actividad_id'); }
    public function usuario()    { return $this->belongsTo(User::class, 'reprogramado_por'); }
}
