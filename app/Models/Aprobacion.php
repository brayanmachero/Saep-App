<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aprobacion extends Model
{
    use HasFactory;

    protected $table = 'aprobaciones';

    protected $fillable = [
        'respuesta_id',
        'aprobador_id',
        'accion',
        'comentario',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function respuesta()
    {
        return $this->belongsTo(Respuesta::class);
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobador_id');
    }
}
