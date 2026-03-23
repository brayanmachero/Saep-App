<?php

namespace App\Models;

use App\Models\CentroCosto;
use Illuminate\Database\Eloquent\Model;

class ProgramaSst extends Model
{
    protected $table = 'programas_sst';

    protected $fillable = ['anio','titulo','descripcion','estado','creado_por'];

    // Alias: views use $prog->nombre, DB stores 'titulo'
    public function getNombreAttribute(): string { return $this->titulo ?? ''; }
    // Stub for missing FK — table has no centro_costo_id yet
    public function centroCosto() { return $this->belongsTo(CentroCosto::class, 'centro_costo_id'); }
    public function responsable() { return $this->belongsTo(User::class, 'responsable_id'); }

    public function categorias()  { return $this->hasMany(SstCategoria::class, 'programa_id'); }
    public function creador()     { return $this->belongsTo(User::class, 'creado_por'); }

    public function getPorcentajeRealizadoAttribute(): int
    {
        $seguimiento = SstSeguimiento::whereHas('actividad', fn($q) =>
            $q->whereHas('categoria', fn($q2) => $q2->where('programa_id', $this->id))
        );
        $programados = (clone $seguimiento)->where('programado', true)->count();
        $realizados  = (clone $seguimiento)->where('realizado', true)->count();
        return $programados > 0 ? (int) round($realizados / $programados * 100) : 0;
    }
}
