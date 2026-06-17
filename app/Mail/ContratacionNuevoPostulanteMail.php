<?php

namespace App\Mail;

use App\Models\PostulanteContratacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContratacionNuevoPostulanteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PostulanteContratacion $postulante) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "RRHH: nuevo postulante — {$this->postulante->folio}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contratacion_nuevo_postulante');
    }
}
