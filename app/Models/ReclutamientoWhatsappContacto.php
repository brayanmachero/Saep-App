<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReclutamientoWhatsappContacto extends Model
{
    public const FINALIDADES = [
        'seguimiento_postulacion' => 'Seguimiento de una postulación',
        'convocatorias_laborales' => 'Convocatorias laborales',
        'bolsa_talentos' => 'Bolsa de talentos',
    ];

    protected $table = 'reclutamiento_whatsapp_contactos';

    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'origen',
        'origen_detalle',
        'consentimiento_whatsapp',
        'consentimiento_aceptado_at',
        'consentimiento_origen',
        'consentimiento_texto',
        'consentimiento_finalidad',
        'consentimiento_version',
        'consentimiento_evidencia_ref',
        'consentimiento_verificado_at',
        'consentimiento_verificado_por',
        'retencion_hasta',
        'consentimiento_ip',
        'consentimiento_user_agent',
        'consentimiento_revocado_at',
        'motivo_revocacion',
    ];

    protected $casts = [
        'consentimiento_whatsapp' => 'boolean',
        'consentimiento_aceptado_at' => 'datetime',
        'consentimiento_verificado_at' => 'datetime',
        'retencion_hasta' => 'date',
        'consentimiento_revocado_at' => 'datetime',
    ];

    public function destinatarios(): HasMany
    {
        return $this->hasMany(ReclutamientoWhatsappDestinatario::class, 'contacto_id');
    }

    public function conversaciones(): HasMany
    {
        return $this->hasMany(ReclutamientoWhatsappConversacion::class, 'contacto_id');
    }

    public function consentimientoVerificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consentimiento_verificado_por');
    }

    public function puedeRecibirCampanias(?string $finalidad = null): bool
    {
        $esElegible = $this->consentimiento_whatsapp
            && $this->consentimiento_aceptado_at !== null
            && $this->consentimiento_revocado_at === null
            && $this->consentimiento_finalidad !== null
            && $this->consentimiento_version !== null
            && $this->consentimiento_evidencia_ref !== null
            && $this->consentimiento_verificado_at !== null
            && $this->consentimiento_verificado_por !== null
            && $this->retencion_hasta !== null
            && $this->retencion_hasta->gte(today());

        return $esElegible && ($finalidad === null || $this->consentimiento_finalidad === $finalidad);
    }

    public function scopeElegiblesParaCampanias($query, ?string $finalidad = null)
    {
        return $query
            ->where('consentimiento_whatsapp', true)
            ->whereNotNull('consentimiento_aceptado_at')
            ->whereNotNull('consentimiento_finalidad')
            ->whereNotNull('consentimiento_version')
            ->whereNotNull('consentimiento_evidencia_ref')
            ->whereNotNull('consentimiento_verificado_at')
            ->whereNotNull('consentimiento_verificado_por')
            ->whereDate('retencion_hasta', '>=', today())
            ->whereNull('consentimiento_revocado_at')
            ->when($finalidad, fn ($contacts) => $contacts->where('consentimiento_finalidad', $finalidad));
    }

    public function scopeConConsentimientoVigente($query)
    {
        return $query->elegiblesParaCampanias();
    }
}
