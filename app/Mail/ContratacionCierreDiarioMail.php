<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ContratacionCierreDiarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $fecha,
        public Collection $postulantes,
        public array $filas,
        public array $resumen
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cierre diario postulaciones RRHH - ' . $this->fecha->format('d/m/Y')
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contratacion_cierre_diario');
    }
}
