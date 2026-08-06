<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        $fecha = Carbon::parse($this->fecha)->locale('es')->isoFormat('dddd D [de] MMMM YYYY');

        $urgencias = $r['total_alertas']
            ?? ($r['total_incompletas'] + $r['total_sin_marcacion'] + ($r['total_revision'] ?? 0));

        $prefijo = $urgencias > 0
            ? "⚠️ [{$urgencias} alertas]"
            : '✅';
        $centroCosto = $r['centro_costo'] ?? null;
        $alcance = $centroCosto ? " — {$centroCosto}" : '';

        return new Envelope(
            subject: "{$prefijo} Reporte Asistencia Talana{$alcance} — {$fecha}",
        );
    }

    public function content(): Content
    {
        $r = $this->reporte;
        $dia = Carbon::parse($this->fecha)->locale('es')->isoFormat('dddd D [de] MMMM YYYY');
        $limiteDetalle = 8;

        return new Content(
            view: 'emails.talana_asistencia_reporte',
            with: [
                'reporte' => $r,
                'dia' => $dia,
                'fecha' => $this->fecha,
                'centroCosto' => $r['centro_costo'] ?? null,
                'limiteDetalle' => $limiteDetalle,
                'incompletasDestacadas' => array_slice($r['incompletas'] ?? [], 0, $limiteDetalle),
                'sinMarcacionDestacados' => array_slice($r['sin_marcacion'] ?? [], 0, $limiteDetalle),
                'revisionDestacados' => array_slice($r['revision'] ?? [], 0, $limiteDetalle),
                'generadoEn' => now()->format('d/m/Y H:i'),
            ],
        );
    }

    public function attachments(): array
    {
        try {
            $xlsx = $this->buildExcel();

            return [
                Attachment::fromData(fn () => $xlsx, $this->nombreArchivoAdjunto())
                    ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Excel ────────────────────────────────────────────────────────────────

    private function buildExcel(): string
    {
        $r = $this->reporte;
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setTitle("Asistencia {$this->fecha}")
            ->setCreator('SAEP Sistema');

        // ── Hoja 1: Resumen ──────────────────────────────────────────────────
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Resumen');

        $titulo = "REPORTE ASISTENCIA — {$this->fecha}";
        if (! empty($r['centro_costo'])) {
            $titulo .= " — {$r['centro_costo']}";
        }
        $this->setCellBold($ws, 'A1', $titulo, 14);
        $ws->mergeCells('A1:D1');
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $ws->setCellValue('A3', 'Categoría');
        $ws->setCellValue('B3', 'Total');
        $this->styleHeader($ws, 'A3:B3');

        $filas = [
            ['Trabajadores activos',                         $r['total_activos']],
            ['✅ Con marcación completa',                    $r['total_completos']],
            ['⚠️ Marcación incompleta (1 sola marca)',       $r['total_incompletas']],
            ['❌ Sin marca con jornada confirmada',          $r['total_sin_marcacion']],
            ['🆕 Contrato reciente sin historial de marca', $r['total_sin_historial'] ?? 0],
            ['😴 Día de descanso (turno)',                  $r['total_descanso'] ?? 0],
            ['📋 Ausencia aprobada',                         $r['total_ausencias'] ?? 0],
            ['◌ Sin evaluación de jornada',                 $r['total_sin_evaluacion'] ?? 0],
            ['🔍 Revisión (anomalías detectadas)',           $r['total_revision'] ?? 0],
        ];

        $row = 4;
        foreach ($filas as [$label, $val]) {
            $ws->setCellValue("A{$row}", $label);
            $ws->setCellValue("B{$row}", $val);
            $row++;
        }

        $ws->getColumnDimension('A')->setWidth(45);
        $ws->getColumnDimension('B')->setWidth(12);

        // ── Hojas: Marcación incompleta (por empresa) ────────────────────────
        foreach ($this->porEmpresa($r['incompletas']) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Incompleta {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Marcación Incompleta — {$empresa}", true);
        }

        // ── Hojas: Sin marcación (por empresa) ────────────────────────────────
        foreach ($this->porEmpresa($r['sin_marcacion']) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Sin Marc. {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Sin Marcación — {$empresa}", false);
        }

        // ── Hojas: Contratos recientes sin historial (por empresa) ───────────
        foreach ($this->porEmpresa($r['sin_historial'] ?? []) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Sin historial {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Contrato reciente sin historial de marca — {$empresa}", false, true);
        }

        // ── Hojas informativas, no son alertas ───────────────────────────────
        foreach ($this->porEmpresa($r['ausencias'] ?? []) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Ausencias {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Ausencias aprobadas — {$empresa}", false, true);
        }
        foreach ($this->porEmpresa($r['sin_evaluacion'] ?? []) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Sin evaluar {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Sin evaluación de jornada — {$empresa}", false, true);
        }

        // ── Hojas: Revisión (descanso + horas anómalas, por empresa) ───────
        foreach ($this->porEmpresa($r['revision'] ?? []) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Revisión {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Requieren Revisión — {$empresa}", true, true);
        }

        // ── Hojas: Completos (por empresa) ───────────────────────────────────
        foreach ($this->porEmpresa($r['completos']) as $empresa => $filas) {
            $ws = $spreadsheet->createSheet();
            $ws->setTitle(mb_substr("Completos {$empresa}", 0, 31));
            $this->escribirHojaPersonas($ws, $filas, "Con Marcación Completa — {$empresa}", true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $content;
    }

    private function nombreArchivoAdjunto(): string
    {
        $centroCosto = $this->reporte['centro_costo'] ?? null;
        if (! $centroCosto) {
            return "asistencia_{$this->fecha}.xlsx";
        }

        $centro = Str::slug($centroCosto);

        return "asistencia_{$this->fecha}_{$centro}.xlsx";
    }

    private function escribirHojaPersonas(Worksheet $ws, array $filas, string $titulo, bool $mostrarMarcas, bool $mostrarMotivo = false): void
    {
        $this->setCellBold($ws, 'A1', $titulo, 12);

        $headers = ['Nombre', 'RUT', 'Centro Costo / Sucursal', 'Cargo', 'Tipo Contrato', 'Desde', 'Hasta'];
        if ($mostrarMotivo) {
            $headers[] = 'Motivo';
        }
        if ($mostrarMarcas) {
            $headers[] = 'Marcas del día';
            $headers[] = '1ra Entrada';
            $headers[] = 'Última Salida';
            $headers[] = 'Horas trabajadas';
        }

        $col = 'A';
        $row = 3;
        foreach ($headers as $h) {
            $ws->setCellValue("{$col}{$row}", $h);
            $col++;
        }
        $rangeCols = 'A'.$row.':'.chr(ord('A') + count($headers) - 1).$row;
        $this->styleHeader($ws, $rangeCols);

        $row = 4;
        foreach ($filas as $f) {
            $cols = [
                $f['nombre'] ?? '—',
                $f['rut'] ?? '—',
                $f['centro_costo'] ?? '—',
                $f['cargo'] ?? '—',
                $f['tipo_contrato'] ?? '—',
                $f['desde'] ?? '—',
                $f['hasta'] ?? '—',
            ];
            if ($mostrarMotivo) {
                $cols[] = $f['motivo'] ?? '—';
            }
            if ($mostrarMarcas) {
                $cols[] = $f['marcas'] ?? '—';
                $cols[] = $f['primera_entrada'] ?? '—';
                $cols[] = $f['ultima_salida'] ?? '—';
                $cols[] = isset($f['horas_trabajadas']) && $f['horas_trabajadas'] !== null
                    ? number_format((float) $f['horas_trabajadas'], 1).'h'
                    : '—';
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

    private function porEmpresa(array $filas): array
    {
        $grupos = [];
        foreach ($filas as $f) {
            $empresa = $f['empresa'] ?? 'Sin empresa';
            $grupos[$empresa][] = $f;
        }
        ksort($grupos);

        return $grupos;
    }

    private function setCellBold(Worksheet $ws, string $cell, string $value, int $size = 12): void
    {
        $ws->setCellValue($cell, $value);
        $ws->getStyle($cell)->getFont()->setBold(true)->setSize($size);
    }

    private function styleHeader(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1e3a5f');
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
}
