<?php

namespace App\Mail;

use App\Models\Respuesta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RespuestaAprobadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Respuesta $respuesta) {}

    public function envelope(): Envelope
    {
        $accion = $this->respuesta->estado === 'Aprobado' ? 'Aprobada' : 'Rechazada';
        return new Envelope(
            subject: "Solicitud {$accion} — " . $this->respuesta->formulario->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.respuesta_aprobada');
    }
}
