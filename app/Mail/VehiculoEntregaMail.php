<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VehiculoEntregaMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;
    private string $pdfContent;
    private string $pdfFilename;

    public function __construct(array $data, string $pdfContent, string $pdfFilename)
    {
        $this->data = $data;
        $this->pdfContent = $pdfContent;
        $this->pdfFilename = $pdfFilename;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Acta de ENTREGA Vehículo - {$this->data['gestion']} - PPU: {$this->data['patente']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vehiculo_entrega',
            with: ['vehiculo' => $this->data],
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
