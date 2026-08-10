<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclutamientoWhatsappDestinatario extends Model
{
    protected $table = 'reclutamiento_whatsapp_destinatarios';

    protected $fillable = [
        'campania_id',
        'contacto_id',
        'estado',
        'meta_message_id',
        'parametros_plantilla',
        'codigo_error',
        'enviado_at',
        'entregado_at',
        'leido_at',
        'respondido_at',
    ];

    protected $casts = [
        'parametros_plantilla' => 'array',
        'enviado_at' => 'datetime',
        'entregado_at' => 'datetime',
        'leido_at' => 'datetime',
        'respondido_at' => 'datetime',
    ];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappCampania::class, 'campania_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappContacto::class, 'contacto_id');
    }
}
