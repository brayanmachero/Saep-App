<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InspeccionPreventivaPdrExcelExport
{
    public function generate(array $analytics, Collection $records, array $filters): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('SAEP')->setTitle('Reporte PDR Inspección Preventiva');
        $this->summary($book, $analytics, $filters);
        $this->detail($book, $records);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/reporte_inspecciones_pdr_' . now()->format('Ymd_His') . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function summary(Spreadsheet $book, array $analytics, array $filters): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Resumen');
        $sheet->mergeCells('A1:F1')->setCellValue('A1', 'PDR INSPECCIÓN PREVENTIVA');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF21064F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->mergeCells('A2:F2')->setCellValue('A2', sprintf(
            'Período: %s a %s · Generado: %s',
            $filters['fecha_desde'] ?? 'Inicio', $filters['fecha_hasta'] ?? 'hoy', now()->format('d/m/Y H:i'),
        ));
        $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ([
            ['Inspecciones', $analytics['total'] ?? 0], ['Condiciones', $analytics['condiciones'] ?? 0],
            ['Medidas', $analytics['medidas'] ?? 0], ['Acción inmediata', $analytics['inmediatas'] ?? 0],
            ['Evidencias', $analytics['evidencias'] ?? 0], ['Centros activos', $analytics['centros_activos'] ?? 0],
        ] as $i => [$label, $value]) {
            $column = chr(65 + $i);
            $sheet->setCellValue("{$column}4", $label)->setCellValue("{$column}5", $value);
            $sheet->getStyle("{$column}4")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFF6B35']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle("{$column}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$column}5")->getFont()->setBold(true)->setSize(14);
        }

        $row = 8;
        foreach (['Por centro' => 'centros', 'Por objetivo' => 'objetivos', 'Frecuencia de medidas' => 'frecuencias', 'Verificación' => 'verificaciones', 'Áreas inspeccionadas' => 'areas'] as $title => $key) {
            $sheet->mergeCells("A{$row}:B{$row}")->setCellValue("A{$row}", $title);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF21064F']],
            ]);
            $row++;
            $sheet->fromArray(['Categoría', 'Cantidad'], null, "A{$row}");
            $this->header($sheet, "A{$row}:B{$row}");
            foreach (($analytics[$key] ?? []) as $label => $count) {
                $sheet->fromArray([$label, $count], null, 'A' . ++$row);
            }
            $row += 3;
        }
        $sheet->getColumnDimension('A')->setWidth(65);
        $sheet->getColumnDimension('B')->setWidth(15);
        foreach (range('C', 'F') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
    }

    private function detail(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Inspecciones');
        $headers = ['Kizeo', 'Fecha', 'Hora', 'Centro', 'Área', 'Objetivo', 'Responsable área', 'Inspector', 'Cargo inspector', 'Condiciones', 'Evidencias', 'Detalle condiciones', 'Medidas', 'Detalle medidas', 'Frecuencias', 'Verificación', 'Responsable medida'];
        $sheet->fromArray($headers, null, 'A1');
        $this->header($sheet, 'A1:Q1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:Q1');
        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->kizeo_record_number, $record->fecha_inspeccion?->format('d/m/Y'), $record->hora_inspeccion,
                $record->centro, $record->area_inspeccionada, $record->objetivo, $record->responsable_area,
                $record->inspector_nombre, $record->inspector_cargo, $record->condiciones_count, $record->evidencias_count,
                $record->condiciones_resumen, $record->medidas_count, $record->medidas_resumen,
                $this->tokenLabel($record->frecuencias_text), $this->tokenLabel($record->verificaciones_text), $record->responsable_medida,
            ], null, "A{$row}");
            $row++;
        }
        foreach (range('A', 'Q') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['L', 'N'], true) ? 55 : 20);
        }
        $sheet->getStyle('A2:Q' . max(2, $row - 1))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    }

    private function header($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFF6B35']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    private function tokenLabel(?string $tokens): ?string
    {
        $values = array_values(array_unique(array_filter(explode('|', trim((string) $tokens, '|')))));

        return $values ? implode(', ', $values) : null;
    }
}
