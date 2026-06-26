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

    public function modulos()
    {
        return $this->belongsToMany(Modulo::class, 'rol_modulo')
            ->withPivot('puede_ver', 'puede_crear', 'puede_editar', 'puede_eliminar')
            ->withTimestamps();
    }

    public function esSuperAdmin(): bool
    {
        return strtoupper((string) $this->codigo) === 'SUPER_ADMIN';
    }

    /**
     * Verificar si este rol tiene acceso a un módulo.
     */
    public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool
    {
        if ($this->esSuperAdmin()) {
            return Modulo::where('slug', $moduloSlug)
                ->where('activo', true)
                ->exists();
        }

        $modulo = $this->modulos()->where('slug', $moduloSlug)->first();
        if (!$modulo) return false;
        return (bool) $modulo->pivot->{$accion};
    }
}
