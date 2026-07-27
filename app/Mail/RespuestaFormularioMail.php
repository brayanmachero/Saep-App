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
    public array $adjuntosOmitidos = [];

    private array $adjuntosIncluidos = [];
    private bool $incluirPdfGenerado = false;

    public function __construct(
        public Respuesta $respuesta,
        private ?string $pdfContent = null,
        private ?string $pdfFilename = null,
    ) {
        $this->schema = json_decode($respuesta->formulario->schema_json ?? '[]', true);
        $this->datos = json_decode($respuesta->datos_json ?? '{}', true);
        $this->planificarAdjuntos();
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

        foreach ($this->adjuntosIncluidos as $item) {
            $attachments[] = Attachment::fromStorageDisk('public', $item['path'])
                ->as($item['name'])
                ->withMime($item['mime']);
        }

        if ($this->incluirPdfGenerado && $this->pdfContent !== null) {
            $attachments[] = Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename ?? ($this->respuesta->formulario->nombre . '.pdf'))
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    private function planificarAdjuntos(): void
    {
        $maxBytes = max(0, (int) config('mail.response_attachment_max_bytes', 25 * 1024 * 1024));
        $usedBytes = 0;
        $pdfSize = strlen($this->pdfContent ?? '');

        // Preserve the generated response PDF whenever it fits the safe budget.
        if ($pdfSize > 0) {
            if ($pdfSize <= $maxBytes) {
                $this->incluirPdfGenerado = true;
                $usedBytes = $pdfSize;
            } else {
                $this->registrarAdjuntoOmitido($this->pdfFilename ?? ($this->respuesta->formulario->nombre . '.pdf'), $pdfSize);
            }
        }

        foreach ($this->adjuntosDelFormulario() as $item) {
            if ($usedBytes + $item['size'] > $maxBytes) {
                $this->registrarAdjuntoOmitido($item['name'], $item['size']);
                continue;
            }

            $this->adjuntosIncluidos[] = $item;
            $usedBytes += $item['size'];
        }
    }

    /**
     * @return array<int, array{path: string, name: string, mime: string, size: int}>
     */
    private function adjuntosDelFormulario(): array
    {
        $attachments = [];
        $disk = Storage::disk('public');

        foreach ($this->schema as $field) {
            if (($field['type'] ?? null) !== 'file' || !isset($this->datos[$field['id']])) {
                continue;
            }

            $fileData = $this->datos[$field['id']];
            $items = isset($fileData[0]['path']) ? $fileData : [$fileData];

            foreach ($items as $item) {
                $path = $item['path'] ?? null;

                if (!$path || !$disk->exists($path)) {
                    continue;
                }

                $attachments[] = [
                    'path' => $path,
                    'name' => $item['name'] ?? basename($path),
                    'mime' => $item['mime'] ?? 'application/octet-stream',
                    'size' => (int) $disk->size($path),
                ];
            }
        }

        return $attachments;
    }

    private function registrarAdjuntoOmitido(string $name, int $size): void
    {
        $this->adjuntosOmitidos[] = [
            'name' => $name,
            'size' => $size,
        ];
    }
}
