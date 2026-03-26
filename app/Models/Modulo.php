<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';

    protected $fillable = [
        'slug', 'nombre', 'descripcion', 'icono', 'grupo', 'orden', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_modulo')
            ->withPivot('puede_ver', 'puede_crear', 'puede_editar', 'puede_eliminar')
            ->withTimestamps();
    }
}
