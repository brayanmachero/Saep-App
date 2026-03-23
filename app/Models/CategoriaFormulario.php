<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaFormulario extends Model
{
    protected $table = 'categorias_formularios';

    protected $fillable = ['nombre','descripcion','icono','color','orden','activo'];

    protected $casts = ['activo' => 'boolean'];

    public function formularios() { return $this->hasMany(Formulario::class, 'categoria_id'); }
}
