<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formulario extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'departamento_id',
        'categoria_id',
        'schema_json',
        'version',
        'activo',
        'fecha_inicio',
        'fecha_fin',
        'frecuencia',
        'requiere_aprobacion',
        'aprobador_rol_id',
        'genera_pdf',
        'template_pdf_id',
        'fuente_trabajadores',
        'creado_por',
    ];

    protected $casts = [
        'activo'              => 'boolean',
        'requiere_aprobacion' => 'boolean',
        'genera_pdf'          => 'boolean',
        'fecha_inicio'        => 'date',
        'fecha_fin'           => 'date',
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaFormulario::class, 'categoria_id');
    }

    public function aprobadorRol()
    {
        return $this->belongsTo(Rol::class, 'aprobador_rol_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function respuestas()
    {
        return $this->hasMany(Respuesta::class);
    }

    public function asignaciones()
    {
        return $this->belongsToMany(User::class, 'formulario_usuario')
                    ->withPivot('estado', 'fecha_limite', 'completado_at')
                    ->withTimestamps();
    }

    public function versiones()
    {
        return $this->hasMany(FormularioVersion::class)->orderByDesc('version');
    }

    /**
     * Check if the form is currently active (within scheduling dates).
     */
    public function estaVigente(): bool
    {
        if (!$this->activo) return false;
        $hoy = now()->startOfDay();
        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio)) return false;
        if ($this->fecha_fin && $hoy->gt($this->fecha_fin)) return false;
        return true;
    }
}
