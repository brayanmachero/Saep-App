<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Charla extends Model
{
    protected $table = 'charlas';

    protected $fillable = [
        'titulo', 'contenido', 'tipo', 'lugar', 'fecha_programada',
        'duracion_minutos', 'creado_por', 'supervisor_id',
        'estado', 'archivos_adjuntos', 'activo', 'fecha_dictado',
    ];

    protected $casts = [
        'fecha_programada'   => 'datetime',
        'fecha_dictado'      => 'datetime',
        'archivos_adjuntos'  => 'array',
        'activo'             => 'boolean',
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function asistentes()
    {
        return $this->hasMany(CharlaAsistente::class, 'charla_id');
    }

    public function getEstadoBadgeAttribute(): string
    {
        return match ($this->estado) {
            'BORRADOR'   => 'secondary',
            'PROGRAMADA' => 'warning',
            'EN_CURSO'   => 'info',
            'COMPLETADA' => 'success',
            'CANCELADA'  => 'danger',
            default      => 'secondary',
        };
    }
}
