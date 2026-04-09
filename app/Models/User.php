<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
        'password', 'must_change_password', 'activo', 'ultimo_acceso',
        'acepta_politica_datos', 'fecha_aceptacion_politica',
        'foto_perfil',
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
     * Accessor/Mutator: normaliza el RUT al guardar (solo dígitos+K),
     * y lo formatea al leer (12.345.678-9).
     */
    protected function rut(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) return null;
                $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $value));
                if (strlen($clean) < 2) return $clean;
                $dv = substr($clean, -1);
                $body = substr($clean, 0, -1);
                $formatted = '';
                $count = 0;
                for ($i = strlen($body) - 1; $i >= 0; $i--) {
                    $formatted = $body[$i] . $formatted;
                    $count++;
                    if ($count % 3 === 0 && $i > 0) $formatted = '.' . $formatted;
                }
                return $formatted . '-' . $dv;
            },
            set: function (?string $value) {
                if (!$value) return null;
                return strtoupper(preg_replace('/[^0-9kK]/', '', $value));
            },
        );
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
            'must_change_password' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }
}
