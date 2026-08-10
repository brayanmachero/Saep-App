<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReclutamientoWhatsappEvento extends Model
{
    protected $table = 'reclutamiento_whatsapp_eventos';

    protected $fillable = [
        'campania_id',
        'contacto_id',
        'destinatario_id',
        'meta_event_id',
        'tipo',
        'datos',
        'ocurrido_at',
    ];

    protected $casts = [
        'datos' => 'array',
        'ocurrido_at' => 'datetime',
    ];
}
