<?php

namespace App\Mail;

use App\Models\LeyKarin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeyKarinDenunciaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeyKarin $caso)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nueva Denuncia Ley Karin — {$this->caso->folio}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ley_karin_denuncia',
        );
    }
}
