<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclutamientoWhatsappMensaje extends Model
{
    protected $table = 'reclutamiento_whatsapp_mensajes';

    protected $fillable = [
        'conversacion_id',
        'direccion',
        'tipo',
        'meta_message_id',
        'contenido',
        'enviado_por',
        'estado',
        'ocurrido_at',
        'entregado_at',
        'leido_at',
    ];

    protected $casts = [
        'contenido' => 'encrypted',
        'ocurrido_at' => 'datetime',
        'entregado_at' => 'datetime',
        'leido_at' => 'datetime',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappConversacion::class, 'conversacion_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
