<?php

namespace App\Mail;

use App\Models\Respuesta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class RespuestaFormularioMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $schema;
    public array $datos;

    public function __construct(public Respuesta $respuesta)
    {
        $this->schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);
        $this->datos = json_decode($respuesta->datos_json ?? '{}', true);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->respuesta->formulario->nombre . ' — Respuesta de ' . ($this->respuesta->usuario->name ?? 'Usuario'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.respuesta_formulario');
    }

    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->schema as $field) {
            if ($field['type'] === 'file' && isset($this->datos[$field['id']])) {
                $fileData = $this->datos[$field['id']];
                $path = $fileData['path'] ?? null;
                if ($path && Storage::disk('public')->exists($path)) {
                    $attachments[] = Attachment::fromStorageDisk('public', $path)
                        ->as($fileData['name'] ?? basename($path))
                        ->withMime($fileData['mime'] ?? 'application/octet-stream');
                }
            }
        }

        // Attach PDF if form generates it and it exists
        if ($this->respuesta->pdf_url && Storage::disk('public')->exists($this->respuesta->pdf_url)) {
            $attachments[] = Attachment::fromStorageDisk('public', $this->respuesta->pdf_url)
                ->as($this->respuesta->formulario->nombre . '.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
