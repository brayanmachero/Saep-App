<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StopReporteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $analytics,
        public string $periodo,
        public ?string $mesLabel = null,
        public string $frecuencia = 'Semanal',
    ) {}

    public function envelope(): Envelope
    {
        $total = $this->analytics['totalRows'] ?? 0;
        $clasif = $this->analytics['clasificacion'] ?? [];
        $neg = $clasif['Negativa'] ?? $clasif['negativa'] ?? 0;
        $label = $this->mesLabel ?? $this->periodo;

        return new Envelope(
            subject: "Reporte {$this->frecuencia} Tarjeta STOP — {$total} obs. ({$neg} neg.) — {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.stop_reporte');
    }
}
