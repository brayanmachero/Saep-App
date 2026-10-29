<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class InventarioCierreDiarioMail extends Mailable
{
    public function __construct(public string $fecha, public Collection $deliveries) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bodega: '.$this->deliveries->count().' entregas sin descontar — '.$this->fecha);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.inventario_cierre_diario');
    }
}
