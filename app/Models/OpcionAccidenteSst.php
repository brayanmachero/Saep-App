<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpcionAccidenteSst extends Model
{
    protected $table = 'opciones_accidente_sst';

    protected $fillable = ['tipo', 'nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function accidentes()
    {
        return $this->belongsToMany(AccidenteSst::class, 'accidente_sst_opcion', 'opcion_id', 'accidente_sst_id');
    }
}
