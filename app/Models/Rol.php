<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'codigo',
        'nombre',
        'puede_crear_forms',
        'puede_aprobar',
        'puede_ver_dashboard',
        'puede_admin_usuarios',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
