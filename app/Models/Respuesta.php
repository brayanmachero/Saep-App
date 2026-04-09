<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Respuesta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'formulario_id',
        'version_form',
        'usuario_id',
        'talana_trabajador_id',
        'departamento_id',
        'estado',
        'datos_json',
        'comentario_solicitante',
        'pdf_url',
        'kizeo_form_id',
        'kizeo_record_id',
        'fecha_resolucion',
    ];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function aprobaciones()
    {
        return $this->hasMany(Aprobacion::class);
    }
}
