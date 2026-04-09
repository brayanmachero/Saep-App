<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormularioCampoOpcion extends Model
{
    protected $table = 'formulario_campo_opciones';

    protected $fillable = [
        'formulario_id',
        'campo_id',
        'valor',
        'creado_por',
    ];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
