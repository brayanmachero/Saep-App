<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstCategoria extends Model
{
    protected $table = 'sst_categorias';

    protected $fillable = ['programa_id','nombre','orden'];

    public function programa()    { return $this->belongsTo(ProgramaSst::class, 'programa_id'); }
    public function actividades() { return $this->hasMany(SstActividad::class, 'categoria_id')->orderBy('orden'); }
}
