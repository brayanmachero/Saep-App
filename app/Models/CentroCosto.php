<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentroCosto extends Model
{
    protected $table = 'centros_costo';

    protected $fillable = [
        'codigo', 'nombre', 'razon_social', 'activo',
        'codigo_talana', 'modalidad_talana', 'nombre_talana', 'talana_vigente', 'talana_sincronizado_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'talana_vigente' => 'boolean',
        'talana_sincronizado_at' => 'datetime',
    ];

    /** Nombre comparable: sin tildes, mayúsculas, solo letras/números y espacios simples. */
    public static function normalizarNombre(?string $nombre): string
    {
        return \Illuminate\Support\Str::of((string) $nombre)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /** Nombre sin el sufijo de modalidad Talana (SUB/EST). */
    public static function nombreBase(?string $nombre): string
    {
        return trim(preg_replace('/\s+(SUB|EST)$/', '', static::normalizarNombre($nombre)));
    }

    public function usuarios() { return $this->hasMany(User::class, 'centro_costo_id'); }
    public function visitas()   { return $this->hasMany(VisitaSst::class, 'centro_costo_id'); }
    public function auditorias(){ return $this->hasMany(AuditoriaSst::class, 'centro_costo_id'); }
    public function accidentes(){ return $this->hasMany(AccidenteSst::class, 'centro_costo_id'); }
}
