<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class TalanaAsistenciaReporteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $reporte,
        public string $fecha,
    ) {}

    public function envelope(): Envelope
    {
        $r = $this->reporte;
        $fecha = \Carbon\Carbon::parse($this->fecha)->locale('es')->isoFormat('dddd D [de] MMMM YYYY');

        $urgencias = $r['total_incompletas'] + $r['total_sin_enrolar'];

        $prefijo = $urgencias > 0
            ? "⚠️ [{$urgencias} alertas]"
            : '✅';

        return new Envelope(
            subject: "{$prefijo} Reporte Asistencia Talana — {$fecha}",
        );
    }

    public function content(): Content
    {
        $r   = $this->reporte;
        $dia = \Carbon\Carbon::parse($this->fecha)->locale('es')->isoFormat('dddd D [de] MMMM YYYY');

        // Agrupar "sin marcación" por centro de costo para el email
        $sinMarcacionPorCC = [];
        foreach ($r['sin_marcacion'] as $t) {
            $cc = $t['centro_costo'] ?? 'Sin clasificar';
            $sinMarcacionPorCC[$cc][] = $t;
        }
        ksort($sinMarcacionPorCC);

        // Agrupar incompletas por centro de costo
        $incompletasPorCC = [];
        foreach ($r['incompletas'] as $t) {
            $cc = $t['centro_costo'] ?? 'Sin clasificar';
            $incompletasPorCC[$cc][] = $t;
        }
        ksort($incompletasPorCC);

        return new Content(
            view: 'emails.talana_asistencia_reporte',
            with: [
                'reporte'            => $r,
                'dia'                => $dia,
                'fecha'              => $this->fecha,
                'sinMarcacionPorCC'  => $sinMarcacionPorCC,
                'incompletasPorCC'   => $incompletasPorCC,
                'generadoEn'         => now()->format('d/m/Y H:i'),
            ],
        );
    }

    public function attachments(): array
    {
        try {
            $xlsx = $this->buildExcel();
            return [
                Attachment::fromData(fn() => $xlsx, "asistencia_{$this->fecha}.xlsx")
                    ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Excel ────────────────────────────────────────────────────────────────

    private function buildExcel(): string
    {
        $r           = $this->reporte;
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Asistencia {$this->fecha}")
            ->setCreator('SAEP Sistema');

        // ── Hoja 1: Resumen ──────────────────────────────────────────────────
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Resumen');

        $this->setCellBold($ws, 'A1', "REPORTE ASISTENCIA — {$this->fecha}", 14);
        $ws->mergeCells('A1:D1');
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $ws->setCellValue('A3', 'Categoría');
        $ws->setCellValue('B3', 'Total');
        $this->styleHeader($ws, 'A3:B3');

        $filas = [
            ['Trabajadores activos',           $r['total_activos']],
            ['✅ Con marcación completa',       $r['total_completos']],
            ['⚠️ Marcación incompleta (1 sola marca)', $r['total_incompletas']],
            ['❌ Sin marcación (activos)',      $r['total_sin_marcacion']],
            ['🆕 Probable nuevo sin enrolar',  $r['total_sin_enrolar']],
        ];

        $row = 4;
        foreach ($filas as [$label, $val]) {
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("B{$row}", $val);
            $row++;
        }

        $ws->getColumnDimension('A')->setWidth(45);
        $ws->getColumnDimension('B')->setWidth(12);

        // ── Hoja 2: Marcación incompleta ─────────────────────────────────────
        if (! empty($r['incompletas'])) {
            $ws2 = $spreadsheet->createSheet();
            $ws2->setTitle('Marcación Incompleta');
            $this->escribirHojaPersonas($ws2, $r['incompletas'], 'Marcación Incompleta', true);
        }

        // ── Hoja 3: Sin marcación ─────────────────────────────────────────────
        if (! empty($r['sin_marcacion'])) {
            $ws3 = $spreadsheet->createSheet();
            $ws3->setTitle('Sin Marcación');
            $this->escribirHojaPersonas($ws3, $r['sin_marcacion'], 'Sin Marcación', false);
        }

        // ── Hoja 4: Probables nuevos ─────────────────────────────────────────
        if (! empty($r['sin_enrolar'])) {
            $ws4 = $spreadsheet->createSheet();
            $ws4->setTitle('Probables Nuevos');
            $this->escribirHojaPersonas($ws4, $r['sin_enrolar'], 'Probables Nuevos Sin Enrolar', false);
        }

        // ── Hoja 5: Completos (para referencia) ──────────────────────────────
        if (! empty($r['completos'])) {
            $ws5 = $spreadsheet->createSheet();
            $ws5->setTitle('Completos');
            $this->escribirHojaPersonas($ws5, $r['completos'], 'Con Marcación Completa', true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $content;
    }

    private function escribirHojaPersonas(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, array $filas, string $titulo, bool $mostrarMarcas): void
    {
        $this->setCellBold($ws, 'A1', $titulo, 12);

        $headers = ['Nombre', 'RUT', 'Centro Costo / Sucursal', 'Cargo', 'Tipo Contrato', 'Desde', 'Hasta'];
        if ($mostrarMarcas) {
            $headers[] = 'Marcas del día';
            $headers[] = '1ra Entrada';
            $headers[] = 'Última Salida';
        }

        $col = 'A';
        $row = 3;
        foreach ($headers as $h) {
            $ws->setCellValue("{$col}{$row}", $h);
            $col++;
        }
        $rangeCols = 'A' . $row . ':' . chr(ord('A') + count($headers) - 1) . $row;
        $this->styleHeader($ws, $rangeCols);

        $row = 4;
        foreach ($filas as $f) {
            $cols = [
                $f['nombre']        ?? '—',
                $f['rut']           ?? '—',
                $f['centro_costo']  ?? '—',
                $f['cargo']         ?? '—',
                $f['tipo_contrato'] ?? '—',
                $f['desde']         ?? '—',
                $f['hasta']         ?? '—',
            ];
            if ($mostrarMarcas) {
                $cols[] = $f['marcas']          ?? '—';
                $cols[] = $f['primera_entrada'] ?? '—';
                $cols[] = $f['ultima_salida']   ?? '—';
            }
            $col = 'A';
            foreach ($cols as $val) {
                $ws->setCellValue("{$col}{$row}", $val);
                $col++;
            }
            $row++;
        }

        // Autowidth
        foreach (range('A', chr(ord('A') + count($headers) - 1)) as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function setCellBold(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $cell, string $value, int $size = 12): void
    {
        $ws->setCellValue($cell, $value);
        $ws->getStyle($cell)->getFont()->setBold(true)->setSize($size);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1e3a5f');
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
}
