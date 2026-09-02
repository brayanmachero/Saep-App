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

        $this->summary($book, $analytics, $records, $filters);
        $this->deliveries($book, $records);
        $this->items($book, $records);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/entregas_bodega_' . now()->format('Ymd_His') . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function summary(Spreadsheet $book, array $analytics, Collection $records, array $filters): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Resumen');
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'ENTREGAS DE BODEGA');
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::PURPLE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $period = trim(($filters['fecha_desde'] ?? 'Inicio') . ' a ' . ($filters['fecha_hasta'] ?? 'hoy'));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', "Periodo: {$period} | Generado: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2:H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $cards = [
            ['Entregas', $analytics['total'] ?? 0],
            ['Unidades EPP', $analytics['unidades'] ?? 0],
            ['Valor referencial (CLP)', $analytics['valor_referencial'] ?? 0],
            ['Precio ref. promedio', $analytics['precio_referencia_promedio'] ?? 0],
            ['Unidades valorizadas', $analytics['unidades_valorizadas'] ?? 0],
            ['Unidades sin precio', $analytics['unidades_sin_precio'] ?? 0],
            ['Personas', $analytics['personas'] ?? 0],
            ['Centros activos', $analytics['centros_activos'] ?? 0],
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
        foreach (['C5', 'D5'] as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('$#,##0');
        }

        $sheet->mergeCells('A7:H7');
        $sheet->setCellValue('A7', 'Los valores son referenciales: usan el precio vigente del catálogo cuando la línea está vinculada a inventario o coincide exactamente por artículo y talla.');
        $sheet->getStyle('A7:H7')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A7:H7')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(7)->setRowHeight(28);

        $columns = [['A', 'B'], ['D', 'E'], ['G', 'H']];
        $sectionIndex = 0;
        foreach ($this->summaryBreakdowns($records) as $title => $values) {
            [$firstColumn, $lastColumn] = $columns[$sectionIndex++];
            $this->writeBreakdown(
                $sheet,
                $firstColumn,
                $lastColumn,
                $title,
                $values,
            );
        }

        foreach (['A', 'D', 'G'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(36);
        }
        foreach (['B', 'E', 'H'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(16);
        }
    }

    private function writeBreakdown($sheet, string $firstColumn, string $lastColumn, string $title, array $values): void
    {
        $row = 8;
        $sheet->mergeCells("{$firstColumn}{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("{$firstColumn}{$row}", $title);
        $this->heading($sheet, "{$firstColumn}{$row}:{$lastColumn}{$row}", self::PURPLE);
        $row++;
        $sheet->fromArray(['Categoria', 'Cantidad'], null, "{$firstColumn}{$row}");
        $this->heading($sheet, "{$firstColumn}{$row}:{$lastColumn}{$row}");
        $row++;

        foreach ($values as $label => $count) {
            $sheet->fromArray([$label, $count], null, "{$firstColumn}{$row}");
            $row++;
        }

        $sheet->fromArray(['Total', array_sum($values)], null, "{$firstColumn}{$row}");
        $sheet->getStyle("{$firstColumn}{$row}:{$lastColumn}{$row}")->getFont()->setBold(true);
    }

    private function summaryBreakdowns(Collection $records): array
    {
        $items = $records->flatMap(fn ($record) => $record->items);

        return [
            'Entregas por centro' => $records
                ->map(fn ($record) => $this->labelOrFallback($record->centro, 'Sin centro informado'))
                ->countBy()
                ->sortDesc()
                ->all(),
            'Articulos por unidades' => $this->itemUnitBreakdown($items, 'articulo', 'Sin articulo informado'),
            'Tallas por unidades' => $this->itemUnitBreakdown($items, 'talla', 'Sin talla informada'),
        ];
    }

    private function itemUnitBreakdown(Collection $items, string $field, string $fallback): array
    {
        return $items
            ->groupBy(fn ($item) => $this->labelOrFallback($item->{$field}, $fallback))
            ->map(fn (Collection $group) => (int) $group->sum('cantidad'))
            ->sortDesc()
            ->all();
    }

    private function labelOrFallback(?string $value, string $fallback): string
    {
        return filled(trim((string) $value)) ? trim((string) $value) : $fallback;
    }

    private function deliveries(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Entregas');
        $headers = ['Kizeo', 'Fecha', 'Persona', 'RUT', 'Centro de costo', 'Registrado por', 'Lineas', 'Unidades', 'Unidades valorizadas', 'Unidades sin precio', 'Valor referencial (CLP)'];
        $sheet->fromArray($headers, null, 'A1');
        $this->heading($sheet, 'A1:K1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:K1');

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
                $record->unidades_valorizadas,
                $record->unidades_sin_precio,
                $record->valor_referencial,
            ], null, "A{$row}");
            $this->zebra($sheet, $row, 'K');
            $row++;
        }
        if ($row > 2) {
            $sheet->getStyle('K2:K'.($row - 1))->getNumberFormat()->setFormatCode('$#,##0');
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['C', 'E', 'F'], true) ? 30 : 17);
        }
    }

    private function items(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Items EPP');
        $headers = ['Kizeo', 'Fecha', 'Persona', 'Centro de costo', 'Articulo EPP', 'Talla', 'Cantidad', 'Precio referencia (CLP)', 'Valor referencial (CLP)', 'Origen del precio'];
        $sheet->fromArray($headers, null, 'A1');
        $this->heading($sheet, 'A1:J1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J1');

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
                    $item->precio_referencia,
                    $item->valor_referencial,
                    $item->origen_precio_referencia ?: 'Sin precio de referencia',
                ], null, "A{$row}");
                $this->zebra($sheet, $row, 'J');
                $row++;
            }
        }
        if ($row > 2) {
            $sheet->getStyle('H2:I'.($row - 1))->getNumberFormat()->setFormatCode('$#,##0');
        }
        foreach (range('A', 'J') as $column) {
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
