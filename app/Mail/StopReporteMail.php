<?php

namespace App\Mail;

use App\Services\StopExcelExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StopReporteMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $excelPath = null;

    public function __construct(
        public array $analytics,
        public string $periodo,
        public ?string $mesLabel = null,
        public string $frecuencia = 'Semanal',
        public array $comparison = [],
        public array $evalDetail = [],
    ) {
        // Generate Excel attachment
        $this->excelPath = (new StopExcelExport())->generate(
            $this->analytics,
            $this->periodo,
            $this->frecuencia,
            $this->comparison,
            $this->evalDetail,
        );
    }

    public function envelope(): Envelope
    {
        $total = $this->analytics['totalRows'] ?? 0;
        $clasif = $this->analytics['clasificacion'] ?? [];
        $neg = $clasif['Negativa'] ?? $clasif['negativa'] ?? 0;
        $label = $this->mesLabel ?? $this->periodo;

        // Para reportes semanales, incluir número de semana anterior
        $weekTag = '';
        if (strtolower($this->frecuencia) === 'semanal') {
            $weekNum = now()->subWeek()->isoFormat('W');
            $weekTag = " — Semana {$weekNum}";
        }

        return new Envelope(
            subject: "Reporte {$this->frecuencia} Tarjeta STOP CCU{$weekTag} — {$total} obs. ({$neg} neg.) — {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.stop_reporte');
    }

    public function attachments(): array
    {
        if (!$this->excelPath || !file_exists($this->excelPath)) {
            return [];
        }

        $label = str_replace(' ', '_', $this->periodo);

        return [
            Attachment::fromPath($this->excelPath)
                ->as("Reporte_STO_CCU_{$this->frecuencia}_{$label}.xlsx")
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    public function __destruct()
    {
        // Cleanup temp file after sending
        if ($this->excelPath && file_exists($this->excelPath)) {
            @unlink($this->excelPath);
        }
    }
}
