<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ObservacionConductaCcuExcelExport
{
    private const SAEP_ORANGE = 'FF5A31';
    private const SAEP_PURPLE = '2D0B64';
    private const GREEN = '166534';
    private const RED = 'B91C1C';
    private const GRAY = 'F8FAFC';

    public function generate(array $analytics, Collection $records, array $filters): string
    {
        $book = new Spreadsheet();
        $book->getProperties()
            ->setCreator('SAEP')
            ->setTitle('Reporte Observaciones de Conducta CCU')
            ->setSubject('Dashboard Kizeo Observaciones CCU');

        $this->buildSummary($book, $analytics, $filters);
        $this->buildDetail($book, $records);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/reporte_observaciones_ccu_' . now()->format('Ymd_His') . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function buildSummary(Spreadsheet $book, array $analytics, array $filters): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Resumen');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'OBSERVACIONES DE CONDUCTA CCU');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::SAEP_PURPLE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $period = trim(($filters['fecha_desde'] ?? 'Inicio') . ' a ' . ($filters['fecha_hasta'] ?? 'hoy'));
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', "Período: {$period} · Generado: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $cards = [
            ['Observaciones', $analytics['total'] ?? 0, self::SAEP_ORANGE],
            ['Positivas', $analytics['positivas'] ?? 0, self::GREEN],
            ['Negativas', $analytics['negativas'] ?? 0, self::RED],
            ['Por revisar', $analytics['por_revisar'] ?? 0, '475569'],
            ['Resultado positivo', ($analytics['porcentaje_positivo'] ?? 0) . '%', self::SAEP_PURPLE],
            ['Centros activos', $analytics['centros_activos'] ?? 0, self::SAEP_ORANGE],
        ];

        foreach ($cards as $index => [$label, $value, $color]) {
            $column = chr(65 + $index);
            $sheet->setCellValue("{$column}4", $label);
            $sheet->setCellValue("{$column}5", $value);
            $sheet->getStyle("{$column}4")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle("{$column}5")->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . $color]]],
            ]);
        }

        $row = 8;
        foreach ([
            'Por centro' => $analytics['centros'] ?? [],
            'Por cargo' => $analytics['cargos'] ?? [],
            'Por antigüedad' => $analytics['antiguedades'] ?? [],
            'Medidas de control' => $analytics['medidas'] ?? [],
        ] as $title => $items) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $title);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::SAEP_PURPLE]],
            ]);
            $row++;
            $sheet->fromArray(['Categoría', 'Cantidad'], null, "A{$row}");
            $this->styleTableHeader($sheet, "A{$row}:B{$row}");
            $row++;
            foreach ($items as $label => $count) {
                $sheet->fromArray([$label, $count], null, "A{$row}");
                $row++;
            }
            $row += 2;
        }

        $sheet->getColumnDimension('A')->setWidth(70);
        $sheet->getColumnDimension('B')->setWidth(14);
        foreach (range('C', 'F') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
    }

    private function buildDetail(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Registros');

        $headers = ['Kizeo', 'Fecha', 'Centro', 'Resultado', 'RUT trabajador', 'Trabajador', 'Cargo', 'Antigüedad', 'Conducta', 'Medida de control', 'Retroalimentación', 'Observador', 'Cargo observador'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleTableHeader($sheet, 'A1:M1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M1');

        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->kizeo_record_number,
                $record->fecha_observacion?->format('d/m/Y'),
                $record->centro,
                $record->clasificacion,
                $record->trabajador_rut,
                $record->trabajador_nombre,
                $record->trabajador_cargo,
                $record->antiguedad_cargo,
                $record->tipo_observacion,
                strtoupper(trim((string) $record->medida_control)) === 'RI'
                    ? 'Reinducción inmediata (RI)'
                    : $record->medida_control,
                $record->retroalimentacion,
                $record->observador_nombre,
                $record->observador_cargo,
            ], null, "A{$row}");
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:M{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . self::GRAY);
            }
            $row++;
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['I', 'K'], true) ? 55 : 20);
        }
        $sheet->getStyle("A2:M" . max(2, $row - 1))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    }

    private function styleTableHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::SAEP_ORANGE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }
}
