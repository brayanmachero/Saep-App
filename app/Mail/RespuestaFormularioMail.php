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

    public function __construct(
        public Respuesta $respuesta,
        private ?string $pdfContent = null,
        private ?string $pdfFilename = null,
    ) {
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

                // Multi-file: array of file objects [{path, name, mime, size}, ...]
                if (isset($fileData[0]['path'])) {
                    foreach ($fileData as $item) {
                        if ($item['path'] && Storage::disk('public')->exists($item['path'])) {
                            $attachments[] = Attachment::fromStorageDisk('public', $item['path'])
                                ->as($item['name'] ?? basename($item['path']))
                                ->withMime($item['mime'] ?? 'application/octet-stream');
                        }
                    }
                }
                // Single file: {path, name, mime, size}
                elseif (isset($fileData['path'])) {
                    $path = $fileData['path'];
                    if ($path && Storage::disk('public')->exists($path)) {
                        $attachments[] = Attachment::fromStorageDisk('public', $path)
                            ->as($fileData['name'] ?? basename($path))
                            ->withMime($fileData['mime'] ?? 'application/octet-stream');
                    }
                }
            }
        }

        // Attach generated PDF if provided
        if ($this->pdfContent !== null) {
            $attachments[] = Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename ?? ($this->respuesta->formulario->nombre . '.pdf'))
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
