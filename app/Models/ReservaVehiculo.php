<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservaVehiculo extends Model
{
    public const ESTADOS_BLOQUEANTES = ['CONFIRMADA', 'EN_USO', 'VENCIDA'];

    /**
     * Estados que representan una ocupacion real y deben mantenerse visibles
     * en el historial semanal de la flota. Las canceladas no se muestran.
     */
    public const ESTADOS_VISIBLES_EN_AGENDA = ['CONFIRMADA', 'EN_USO', 'VENCIDA', 'DEVUELTA'];

    public const ESTADOS = [
        'CONFIRMADA' => 'Confirmada',
        'CANCELADA' => 'Cancelada',
        'EN_USO' => 'En uso',
        'DEVUELTA' => 'Devuelta',
        'VENCIDA' => 'Vencida',
    ];

    public const TIPOS_EVENTUALIDAD = [
        'RETRASO_DEVOLUCION' => 'Retraso en la devolucion',
        'INCIDENTE_VEHICULO' => 'Incidente con el vehiculo',
        'OTRA_EVENTUALIDAD' => 'Otra eventualidad',
    ];

    protected $guarded = ['id'];

    protected $table = 'reservas_vehiculos';

    protected $casts = [
        'inicio' => 'datetime',
        'termino' => 'datetime',
        'cancelada_at' => 'datetime',
        'recordatorio_enviado_at' => 'datetime',
        'vencimiento_notificado_at' => 'datetime',
        'calendar_synced_at' => 'datetime',
        'kizeo_pushed_at' => 'datetime',
        'kizeo_synced_at' => 'datetime',
        'entregada_at' => 'datetime',
        'devuelta_at' => 'datetime',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ReservaVehiculoEvento::class)->latest();
    }

    public function estaVigente(): bool
    {
        return in_array($this->estado, self::ESTADOS_BLOQUEANTES, true);
    }

    /**
     * Una ficha enviada por Push a Kizeo queda pendiente en la bandeja móvil;
     * no equivale a un acta firmada. Solo el retorno del acta marca la entrega
     * o devolución como trazable e inmodificable.
     */
    public function tieneActaKizeoCompletada(): bool
    {
        return $this->entregada_at !== null
            || $this->devuelta_at !== null
            || filled($this->kizeo_synced_at)
            || filled($this->kizeo_entrega_sharepoint_path)
            || filled($this->kizeo_devolucion_sharepoint_path);
    }

    public function tieneFichaKizeoPendiente(): bool
    {
        return ! $this->tieneActaKizeoCompletada()
            && (filled($this->kizeo_form_id)
                || filled($this->kizeo_data_id)
                || $this->kizeo_pushed_at !== null);
    }
}
