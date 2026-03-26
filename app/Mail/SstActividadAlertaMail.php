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
        public string $tipo = 'asignacion' // asignacion | vencimiento | vencida
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'asignacion'  => 'Nueva actividad asignada: ' . $this->actividad->nombre,
            'vencimiento' => 'Actividad próxima a vencer: ' . $this->actividad->nombre,
            'vencida'     => '⚠ Actividad vencida: ' . $this->actividad->nombre,
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
