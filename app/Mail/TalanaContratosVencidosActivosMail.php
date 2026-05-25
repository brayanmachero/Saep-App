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

class TalanaContratosVencidosActivosMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $contratos,
    ) {}

    public function envelope(): Envelope
    {
        $total = count($this->contratos);
        return new Envelope(
            subject: "🔴 Alerta: {$total} contrato(s) vencido(s) con trabajador aún activo — requiere revisión",
        );
    }

    public function content(): Content
    {
        $criticos  = count(array_filter($this->contratos, fn($c) => $c['diasVencido'] > 30));
        $recientes = count($this->contratos) - $criticos;

        // Agrupar por sucursal / centro de costo, ordenados alfabéticamente
        $porCC = [];
        foreach ($this->contratos as $c) {
            $cc = $c['sucursal']['nombre'] ?? $c['centroCosto']['nombre'] ?? 'Sin clasificar';
            $porCC[$cc][] = $c;
        }
        ksort($porCC);

        return new Content(
            view: 'emails.talana_contratos_vencidos_activos',
            with: [
                'porCC'      => $porCC,
                'cntCritico' => $criticos,
                'cntReciente'=> $recientes,
                'total'      => count($this->contratos),
                'generadoEn' => now()->format('d/m/Y H:i'),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn() => $this->buildExcel(),
                'contratos_vencidos_activos_' . now()->format('Ymd') . '.xlsx'
            )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    private function buildExcel(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vencidos Activos');

        // Ordenar por CC (A-Z) luego por más vencido primero
        $contratos = $this->contratos;
        usort($contratos, function ($a, $b) {
            $ccA = $a['sucursal']['nombre'] ?? $a['centroCosto']['nombre'] ?? '';
            $ccB = $b['sucursal']['nombre'] ?? $b['centroCosto']['nombre'] ?? '';
            return $ccA !== $ccB ? strcmp($ccA, $ccB) : $b['diasVencido'] - $a['diasVencido'];
        });

        // Encabezados
        $headers = ['N°', 'Trabajador', 'RUT', 'Cargo', 'Tipo Contrato', 'Centro de Costo', 'Venció el', 'Días Vencido', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => '0F1B4C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');

        // Filas de datos
        $row = 2;
        foreach ($contratos as $i => $c) {
            $emp     = $c['empleadoDetails'];
            $nombre  = trim(($emp['nombre'] ?? '') . ' ' . ($emp['apellidoPaterno'] ?? '') . ' ' . ($emp['apellidoMaterno'] ?? ''));
            $cc      = $c['sucursal']['nombre'] ?? $c['centroCosto']['nombre'] ?? 'Sin clasificar';
            $tipo    = $c['tipoContratoDetails']['nombre'] ?? '';
            $esCrit  = $c['diasVencido'] > 30;
            $bgColor = $esCrit ? 'FEE2E2' : 'FFF7ED';

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $nombre);
            $sheet->setCellValue("C{$row}", $emp['rut'] ?? '');
            $sheet->setCellValue("D{$row}", $c['cargo'] ?? '');
            $sheet->setCellValue("E{$row}", $tipo);
            $sheet->setCellValue("F{$row}", $cc);
            $sheet->setCellValue("G{$row}", $c['hasta']);
            $sheet->setCellValue("H{$row}", $c['diasVencido']);
            $sheet->setCellValue("I{$row}", $esCrit ? 'CRÍTICO' : 'Reciente');

            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'fgColor' => ['rgb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            if ($esCrit) {
                $sheet->getStyle("I{$row}")->getFont()->setBold(true)->getColor()->setRGB('991B1B');
            }

            $row++;
        }

        // Auto-tamaño de columnas
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Formato numérico para N° y Días
        if ($row > 2) {
            $sheet->getStyle("A2:A" . ($row - 1))->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle("H2:H" . ($row - 1))->getNumberFormat()->setFormatCode('0');
        }

        // Escribir en memoria
        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'r+');
        $writer->save($stream);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
