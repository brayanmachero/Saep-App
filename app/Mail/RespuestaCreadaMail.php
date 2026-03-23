<?php

namespace App\Mail;

use App\Models\Respuesta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RespuestaCreadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Respuesta $respuesta) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva Solicitud Pendiente — ' . $this->respuesta->formulario->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.respuesta_creada');
    }
}
