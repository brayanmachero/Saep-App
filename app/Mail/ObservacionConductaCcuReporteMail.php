<?php

namespace App\Mail;

use App\Services\ObservacionConductaCcuExcelExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ObservacionConductaCcuReporteMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $excelPath = null;

    public function __construct(
        public array $analytics,
        public Collection $records,
        public array $filters,
        public string $dashboardUrl,
        public string $recipientName,
        bool $attachExcel = true,
    ) {
        if ($attachExcel) {
            $this->excelPath = (new ObservacionConductaCcuExcelExport())->generate(
                $this->analytics,
                $this->records,
                $this->filters,
            );
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Reporte Observaciones de Conducta CCU - %d registros (%d hallazgos)',
                $this->analytics['total'] ?? 0,
                $this->analytics['negativas'] ?? 0,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.observaciones_conducta_ccu_reporte');
    }

    public function attachments(): array
    {
        if (!$this->excelPath || !file_exists($this->excelPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->excelPath)
                ->as('reporte_observaciones_ccu_' . now()->format('Ymd_His') . '.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    public function __destruct()
    {
        if ($this->excelPath && file_exists($this->excelPath)) {
            @unlink($this->excelPath);
        }
    }
}
