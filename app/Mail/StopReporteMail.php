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
        public array $stats,
        public array $topNegTrabajadores,
        public array $topPosTrabajadores,
        public array $negPorTipo,
        public array $posPorTipo,
        public array $centros,
        public array $areas,
        public array $topObservadores,
        public array $antiguedades,
        public string $periodo,
        public ?string $mesLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        $total = $this->stats['total'] ?? 0;
        $neg = $this->stats['negativas'] ?? 0;
        $label = $this->mesLabel ?? $this->periodo;

        return new Envelope(
            subject: "Reporte Tarjeta STOP — {$total} obs. ({$neg} neg.) — {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.stop_reporte');
    }
}
