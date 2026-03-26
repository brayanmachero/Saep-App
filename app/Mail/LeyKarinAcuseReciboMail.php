<?php

namespace App\Mail;

use App\Models\LeyKarin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeyKarinAcuseReciboMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeyKarin $caso)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Acuse de Recibo — Denuncia {$this->caso->folio}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ley_karin_acuse_recibo',
        );
    }
}
