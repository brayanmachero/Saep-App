<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CharlaTrackingReporteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $stats,
        public array $pendientesPorUsuario,
        public array $resumenSemanal,
        public string $periodo,
    ) {}

    public function envelope(): Envelope
    {
        $tasa = $this->stats['tasa_cumplimiento'] ?? 0;
        return new Envelope(
            subject: "📊 Reporte Semanal Charlas SST — Cumplimiento {$tasa}% — {$this->periodo}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.charla_tracking_reporte');
    }
}
