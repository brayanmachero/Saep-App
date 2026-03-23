<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SstActividad extends Model
{
    protected $table = 'sst_actividades';

    protected $fillable = ['categoria_id','nombre','descripcion','responsable','orden'];

    public function categoria()   { return $this->belongsTo(SstCategoria::class, 'categoria_id'); }
    public function seguimiento() { return $this->hasMany(SstSeguimiento::class, 'actividad_id')->orderBy('mes'); }
    public function planAccion()  { return $this->hasMany(SstPlanAccion::class, 'actividad_id'); }

    public function getSeguimientoPorMesAttribute(): array
    {
        $meses = array_fill(1, 12, ['programado'=>false,'realizado'=>false,'observacion'=>null]);
        foreach ($this->seguimiento as $s) {
            $meses[$s->mes] = ['programado'=>(bool)$s->programado,'realizado'=>(bool)$s->realizado,'observacion'=>$s->observacion];
        }
        return $meses;
    }
}
