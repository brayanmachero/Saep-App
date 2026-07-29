<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EntregaBodegaExcelExport
{
    private const PURPLE = '2D0B64';
    private const ORANGE = 'FF5A31';
    private const LIGHT = 'F8FAFC';

    public function generate(array $analytics, Collection $records, array $filters): string
    {
        $book = new Spreadsheet();
        $book->getProperties()
            ->setCreator('SAEP')
            ->setTitle('Entregas de Bodega')
            ->setSubject('Control de Entrega Bodega Kizeo');

        $this->summary($book, $analytics, $filters);
        $this->deliveries($book, $records);
        $this->items($book, $records);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/entregas_bodega_' . now()->format('Ymd_His') . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function summary(Spreadsheet $book, array $analytics, array $filters): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Resumen');
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'ENTREGAS DE BODEGA');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::PURPLE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $period = trim(($filters['fecha_desde'] ?? 'Inicio') . ' a ' . ($filters['fecha_hasta'] ?? 'hoy'));
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', "Periodo: {$period} | Generado: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $cards = [
            ['Entregas', $analytics['total'] ?? 0],
            ['Unidades EPP', $analytics['unidades'] ?? 0],
            ['Lineas de detalle', $analytics['lineas'] ?? 0],
            ['Personas', $analytics['personas'] ?? 0],
            ['Centros activos', $analytics['centros_activos'] ?? 0],
            ['Promedio por entrega', $analytics['promedio_unidades'] ?? 0],
        ];

        foreach ($cards as $index => [$label, $value]) {
            $column = chr(65 + $index);
            $sheet->setCellValue("{$column}4", $label);
            $sheet->setCellValue("{$column}5", $value);
            $sheet->getStyle("{$column}4")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::ORANGE]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getStyle("{$column}5")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("{$column}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getColumnDimension($column)->setWidth(21);
        }

        $row = 8;
        foreach (['Entregas por centro' => $analytics['centros'] ?? [], 'Articulos por unidades' => $analytics['articulos'] ?? [], 'Tallas por unidades' => $analytics['tallas'] ?? []] as $title => $values) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $title);
            $this->heading($sheet, "A{$row}:B{$row}", self::PURPLE);
            $row++;
            $sheet->fromArray(['Categoria', 'Cantidad'], null, "A{$row}");
            $this->heading($sheet, "A{$row}:B{$row}");
            $row++;
            foreach ($values as $label => $count) {
                $sheet->fromArray([$label, $count], null, "A{$row}");
                $row++;
            }
            $row += 2;
        }
        $sheet->getColumnDimension('A')->setWidth(65);
        $sheet->getColumnDimension('B')->setWidth(16);
    }

    private function deliveries(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Entregas');
        $headers = ['Kizeo', 'Fecha', 'Persona', 'RUT', 'Centro de costo', 'Registrado por', 'Lineas', 'Unidades'];
        $sheet->fromArray($headers, null, 'A1');
        $this->heading($sheet, 'A1:H1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1');

        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->kizeo_record_number ?: $record->kizeo_data_id,
                $record->fecha_pedido?->format('d/m/Y'),
                $record->nombre,
                $record->rut,
                $record->centro,
                $record->registrado_por,
                $record->lineas_count,
                $record->unidades_total,
            ], null, "A{$row}");
            $this->zebra($sheet, $row, 'H');
            $row++;
        }
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['C', 'E', 'F'], true) ? 30 : 17);
        }
    }

    private function items(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Items EPP');
        $headers = ['Kizeo', 'Fecha', 'Persona', 'Centro de costo', 'Articulo EPP', 'Talla', 'Cantidad'];
        $sheet->fromArray($headers, null, 'A1');
        $this->heading($sheet, 'A1:G1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G1');

        $row = 2;
        foreach ($records as $record) {
            foreach ($record->items as $item) {
                $sheet->fromArray([
                    $record->kizeo_record_number ?: $record->kizeo_data_id,
                    $record->fecha_pedido?->format('d/m/Y'),
                    $record->nombre,
                    $record->centro,
                    $item->articulo,
                    $item->talla,
                    $item->cantidad,
                ], null, "A{$row}");
                $this->zebra($sheet, $row, 'G');
                $row++;
            }
        }
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['C', 'D', 'E'], true) ? 30 : 17);
        }
    }

    private function heading($sheet, string $range, string $color = self::ORANGE): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    private function zebra($sheet, int $row, string $lastColumn): void
    {
        if ($row % 2 === 0) {
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . self::LIGHT);
        }
    }
}
