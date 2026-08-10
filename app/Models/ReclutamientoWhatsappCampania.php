<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReclutamientoWhatsappCampania extends Model
{
    public const ESTADOS = [
        'borrador',
        'pendiente_aprobacion',
        'aprobada',
        'programada',
        'enviando',
        'completada',
        'pausada',
        'cancelada',
        'fallida',
    ];

    protected $table = 'reclutamiento_whatsapp_campanias';

    protected $fillable = [
        'nombre',
        'descripcion',
        'plantilla_id',
        'plantilla_nombre',
        'plantilla_idioma',
        'categoria',
        'finalidad',
        'estado',
        'filtro_destinatarios',
        'destinatarios_estimados',
        'enviados',
        'entregados',
        'leidos',
        'fallidos',
        'programada_para',
        'creada_por',
        'aprobada_por',
        'aprobada_at',
    ];

    protected $casts = [
        'filtro_destinatarios' => 'array',
        'programada_para' => 'datetime',
        'aprobada_at' => 'datetime',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappPlantilla::class, 'plantilla_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(ReclutamientoWhatsappDestinatario::class, 'campania_id');
    }
}
