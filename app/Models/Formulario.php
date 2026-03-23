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
        'schema_json',
        'version',
        'activo',
        'requiere_aprobacion',
        'aprobador_rol_id',
        'genera_pdf',
        'template_pdf_id',
        'fuente_trabajadores',
        'creado_por',
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
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
}
