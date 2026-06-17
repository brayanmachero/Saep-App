<?php

namespace App\Mail;

use App\Models\PostulanteContratacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContratacionAcuseReciboMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PostulanteContratacion $postulante) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmación de postulación — {$this->postulante->folio}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contratacion_acuse_recibo');
    }
}
