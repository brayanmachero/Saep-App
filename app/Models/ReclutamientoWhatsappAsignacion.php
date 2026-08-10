<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclutamientoWhatsappAsignacion extends Model
{
    protected $table = 'reclutamiento_whatsapp_asignaciones';

    protected $fillable = [
        'conversacion_id',
        'asignada_a',
        'asignada_por',
        'accion',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ReclutamientoWhatsappConversacion::class, 'conversacion_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_por');
    }
}
