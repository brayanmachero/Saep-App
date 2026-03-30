<?php

namespace App\Mail;

use App\Models\SstActividad;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SstActividadAlertaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SstActividad $actividad,
        public string $tipo = 'asignacion' // asignacion | vencimiento | vencida | recordatorio | seguimiento_pendiente
    ) {}

    public function envelope(): Envelope
    {
        $periodicidadLabel = \App\Models\SstActividad::periodicidadesMap()[$this->actividad->periodicidad] ?? '';

        $subjects = [
            'asignacion'            => 'Nueva actividad asignada: ' . $this->actividad->nombre,
            'vencimiento'           => 'Actividad próxima a vencer: ' . $this->actividad->nombre,
            'vencida'               => '⚠ Actividad vencida: ' . $this->actividad->nombre,
            'recordatorio'          => "Recordatorio ({$periodicidadLabel}): " . $this->actividad->nombre,
            'seguimiento_pendiente' => 'Seguimiento pendiente: ' . $this->actividad->nombre,
        ];

        return new Envelope(
            subject: $subjects[$this->tipo] ?? $subjects['asignacion'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sst_actividad_alerta');
    }
}
