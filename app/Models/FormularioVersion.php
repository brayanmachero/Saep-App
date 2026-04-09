<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormularioVersion extends Model
{
    protected $table = 'formulario_versiones';

    protected $fillable = [
        'formulario_id',
        'version',
        'schema_json',
        'modificado_por',
        'nota',
    ];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class);
    }

    public function modificador()
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }
}
