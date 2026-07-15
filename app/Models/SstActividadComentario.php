<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstActividadComentario extends Model
{
    protected $table = 'sst_actividad_comentarios';

    protected $fillable = [
        'actividad_id',
        'user_id',
        'comentario',
    ];

    public function actividad()
    {
        return $this->belongsTo(SstActividad::class, 'actividad_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
