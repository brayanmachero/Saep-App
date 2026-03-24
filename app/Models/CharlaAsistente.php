<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharlaAsistente extends Model
{
    public $timestamps = false;

    protected $table = 'charla_asistentes';

    protected $fillable = [
        'charla_id', 'usuario_id', 'estado',
        'firma_imagen', 'fecha_firma', 'ip_address', 'user_agent',
        'geolatitud', 'geolongitud', 'documento_hash',
        'fecha_asignacion',
    ];

    protected $casts = [
        'fecha_firma'       => 'datetime',
        'fecha_asignacion'  => 'datetime',
    ];

    public function charla()
    {
        return $this->belongsTo(Charla::class, 'charla_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
