<?php

namespace App\Mail;

use App\Models\PostulanteContratacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContratacionNuevoPostulanteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PostulanteContratacion $postulante) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nuevo Postulante — {$this->postulante->folio}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contratacion_nuevo_postulante');
    }
}
