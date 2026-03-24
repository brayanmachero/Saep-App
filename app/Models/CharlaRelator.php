<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharlaRelator extends Model
{
    public $timestamps = false;

    protected $table = 'charla_relatores';

    protected $fillable = [
        'charla_id', 'usuario_id', 'rol_relator', 'estado',
        'firma_imagen', 'fecha_firma', 'ip_address', 'user_agent',
        'documento_hash', 'geolatitud', 'geolongitud', 'fecha_asignacion',
    ];

    protected $casts = [
        'fecha_firma'      => 'datetime',
        'fecha_asignacion' => 'datetime',
        'geolatitud'       => 'decimal:7',
        'geolongitud'      => 'decimal:7',
    ];

    public function charla()
    {
        return $this->belongsTo(Charla::class, 'charla_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function getRolLabelAttribute(): string
    {
        return match ($this->rol_relator) {
            'RELATOR'        => 'Relator / Instructor',
            'SUPERVISOR_CPHS' => 'Supervisor / CPHS',
            'INSTRUCTOR'     => 'Instructor Externo',
            default          => $this->rol_relator,
        };
    }
}
