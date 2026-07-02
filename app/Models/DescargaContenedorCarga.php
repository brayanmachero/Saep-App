<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescargaContenedorCarga extends Model
{
    protected $table = 'descarga_contenedor_cargas';

    protected $fillable = [
        'nombre',
        'origen',
        'filas_detectadas',
        'filas_creadas',
        'filas_con_alertas',
        'raw_payload',
        'creado_por',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function descargas()
    {
        return $this->hasMany(DescargaContenedor::class, 'carga_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
