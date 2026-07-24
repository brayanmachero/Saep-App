<?php

namespace App\Mail;

use App\Services\InspeccionPreventivaPdrExcelExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class InspeccionPreventivaPdrReporteMail extends Mailable
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
            $this->excelPath = (new InspeccionPreventivaPdrExcelExport())->generate($analytics, $records, $filters);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf(
            'Reporte Inspecciones Preventivas PDR - %d inspecciones (%d condiciones)',
            $this->analytics['total'] ?? 0, $this->analytics['condiciones'] ?? 0,
        ));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.inspecciones_preventivas_pdr_reporte');
    }

    public function attachments(): array
    {
        return $this->excelPath && file_exists($this->excelPath)
            ? [Attachment::fromPath($this->excelPath)->as('reporte_inspecciones_pdr_' . now()->format('Ymd_His') . '.xlsx')->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]
            : [];
    }

    public function __destruct()
    {
        if ($this->excelPath && file_exists($this->excelPath)) {
            @unlink($this->excelPath);
        }
    }
}
