<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'azure_oid', 'talana_id', 'name', 'apellido_paterno', 'apellido_materno',
        'email', 'rut', 'departamento_id', 'rol_id', 'cargo_id', 'centro_costo_id',
        'tipo_nomina', 'razon_social', 'fecha_nacimiento', 'nacionalidad',
        'sexo', 'estado_civil', 'fecha_ingreso', 'telefono',
        'password', 'activo', 'ultimo_acceso',
        'acepta_politica_datos', 'fecha_aceptacion_politica',
    ];

    public function departamento() { return $this->belongsTo(Departamento::class); }
    public function rol()           { return $this->belongsTo(Rol::class); }
    public function cargo()         { return $this->belongsTo(Cargo::class); }
    public function centroCosto()   { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function consentimientos() { return $this->hasMany(ConsentimientoDatos::class); }
    public function solicitudesArco() { return $this->hasMany(SolicitudArco::class); }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->name . ' ' . $this->apellido_paterno . ' ' . $this->apellido_materno);
    }

    /**
     * Verificar si el usuario tiene acceso a un módulo del sistema.
     */
    public function tieneAcceso(string $moduloSlug, string $accion = 'puede_ver'): bool
    {
        if (!$this->rol) return false;
        return $this->rol->tieneAcceso($moduloSlug, $accion);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'acepta_politica_datos' => 'boolean',
            'fecha_aceptacion_politica' => 'datetime',
        ];
    }
}
