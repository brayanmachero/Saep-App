<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReclutamientoWhatsappConversacion extends Model
{
    public const ESTADOS = ['nueva', 'asignada', 'en_atencion', 'esperando_respuesta', 'resuelta', 'cerrada'];

    protected $table = 'reclutamiento_whatsapp_conversaciones';

    protected $fillable = [
        'contacto_id',
        'campania_origen_id',
        'estado',
        'asignada_a',
        'ultimo_mensaje_preview',
        'ultimo_mensaje_at',
        'ultimo_mensaje_entrante_at',
        'iniciada_at',
        'cerrada_at',
    ];

    protected $casts = [
        'ultimo_mensaje_preview' => 'encrypted',
        'ultimo_mensaje_at' => 'datetime',
        'ultimo_mensaje_entrante_at' => 'datetime',
        'iniciada_at' => 'datetime',
        'cerrada_at' => 'datetime',
    ];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappContacto::class, 'contacto_id');
    }

    public function campaniaOrigen(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappCampania::class, 'campania_origen_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ReclutamientoWhatsappMensaje::class, 'conversacion_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(ReclutamientoWhatsappAsignacion::class, 'conversacion_id');
    }
}
