<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReclutamientoWhatsappPlantilla extends Model
{
    protected $table = 'reclutamiento_whatsapp_plantillas';

    protected $fillable = [
        'nombre_meta',
        'idioma',
        'categoria',
        'estado',
        'componentes',
        'sincronizada_at',
    ];

    protected $casts = [
        'componentes' => 'array',
        'sincronizada_at' => 'datetime',
    ];

    public function campanias(): HasMany
    {
        return $this->hasMany(ReclutamientoWhatsappCampania::class, 'plantilla_id');
    }
}
