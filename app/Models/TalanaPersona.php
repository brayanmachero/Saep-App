<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TalanaPersona extends Model
{
    protected $table = 'talana_personas';

    protected $fillable = [
        'talana_id', 'rut', 'nombre', 'apellido_paterno', 'apellido_materno',
        'email', 'fecha_nacimiento', 'activo', 'synced_at',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo'           => 'boolean',
        'synced_at'        => 'datetime',
    ];

    public function contratos()
    {
        return $this->hasMany(TalanaContrato::class, 'persona_talana_id', 'talana_id');
    }

    public function marcas()
    {
        return $this->hasMany(TalanaMarca::class, 'persona_talana_id', 'talana_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }
}
