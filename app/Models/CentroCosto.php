<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentroCosto extends Model
{
    protected $table = 'centros_costo';

    protected $fillable = ['codigo','nombre','razon_social','activo'];

    protected $casts = ['activo' => 'boolean'];

    public function usuarios() { return $this->hasMany(User::class, 'centro_costo_id'); }
    public function visitas()   { return $this->hasMany(VisitaSst::class, 'centro_costo_id'); }
    public function auditorias(){ return $this->hasMany(AuditoriaSst::class, 'centro_costo_id'); }
    public function accidentes(){ return $this->hasMany(AccidenteSst::class, 'centro_costo_id'); }
}
