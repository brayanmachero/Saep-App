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
            ->setTitle('Consumo EPP por centro de costo')
            ->setSubject('Entregas y devoluciones Kizeo aplicadas a Inventario');

        $this->summary($book, $analytics, $filters);
        $this->operations($book, $records);
        $this->items($book, $records);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/consumo_epp_'.now()->format('Ymd_His').'.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function summary(Spreadsheet $book, array $analytics, array $filters): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Resumen neto');
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'CONSUMO EPP · ENTREGAS Y DEVOLUCIONES');
        $this->title($sheet, 'A1:H1');
        $sheet->getRowDimension(1)->setRowHeight(30);

        $period = trim(($filters['fecha_desde'] ?? 'Inicio') . ' a ' . ($filters['fecha_hasta'] ?? 'hoy'));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', "Periodo: {$period} | Generado: ".now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2:H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $cards = [
            ['Comprobantes aplicados', $analytics['total'] ?? 0],
            ['Unidades entregadas', $analytics['entregadas'] ?? 0],
            ['Unidades devueltas', $analytics['devueltas'] ?? 0],
            ['Consumo neto', $analytics['netas'] ?? 0],
            ['Valor entregado (CLP)', $analytics['valor_entregado'] ?? 0],
            ['Valor devuelto (CLP)', $analytics['valor_devuelto'] ?? 0],
            ['Valor neto (CLP)', $analytics['valor_neto'] ?? 0],
            ['Centros imputados', $analytics['centros_activos'] ?? 0],
        ];
        foreach ($cards as $index => [$label, $value]) {
            $column = chr(65 + $index);
            $sheet->setCellValue("{$column}4", $label);
            $sheet->setCellValue("{$column}5", $value);
            $this->heading($sheet, "{$column}4");
            $sheet->getStyle("{$column}5")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("{$column}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getColumnDimension($column)->setWidth(22);
        }
        $sheet->getStyle('E5:G5')->getNumberFormat()->setFormatCode('$#,##0');

        $sheet->mergeCells('A7:H7');
        $sheet->setCellValue('A7', 'Fuente de verdad: movimientos de Inventario vigentes. Las devoluciones reducen el consumo del mismo centro de costo y los valores son referenciales.');
        $sheet->getStyle('A7:H7')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A7:H7')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(7)->setRowHeight(28);

        $this->breakdown(
            $sheet,
            9,
            'Consumo por centro de costo',
            ['Centro de costo', 'Comprobantes', 'Entregado', 'Devuelto', 'Neto', 'Valor neto (CLP)'],
            collect($analytics['centros'] ?? [])->map(fn (array $row) => [
                $row['centro'], $row['comprobantes'], $row['entregadas'], $row['devueltas'], $row['netas'], $row['valor_neto'],
            ])->all(),
            [2, 3, 4, 5],
            [6],
        );
        $articleStart = 12 + count($analytics['centros'] ?? []);
        $this->breakdown(
            $sheet,
            $articleStart,
            'Artículos por consumo neto',
            ['Artículo EPP', 'Entregado', 'Devuelto', 'Neto', 'Valor neto (CLP)'],
            collect($analytics['articulos'] ?? [])->map(fn (array $row) => [
                $row['label'], $row['entregadas'], $row['devueltas'], $row['netas'], $row['valor_neto'],
            ])->all(),
            [2, 3, 4],
            [5],
        );
    }

    private function breakdown($sheet, int $row, string $title, array $headers, array $rows, array $quantityColumns, array $currencyColumns): void
    {
        $lastColumn = chr(64 + count($headers));
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("A{$row}", $title);
        $this->heading($sheet, "A{$row}:{$lastColumn}{$row}", self::PURPLE);
        $row++;
        $sheet->fromArray($headers, null, "A{$row}");
        $this->heading($sheet, "A{$row}:{$lastColumn}{$row}");
        $row++;
        foreach ($rows as $values) {
            $sheet->fromArray($values, null, "A{$row}");
            $this->zebra($sheet, $row, $lastColumn);
            $row++;
        }
        if ($rows !== []) {
            $start = $row - count($rows);
            foreach ($quantityColumns as $column) {
                $sheet->getStyle(chr(64 + $column)."{$start}:".chr(64 + $column).($row - 1))->getNumberFormat()->setFormatCode('#,##0.###');
            }
            foreach ($currencyColumns as $column) {
                $sheet->getStyle(chr(64 + $column)."{$start}:".chr(64 + $column).($row - 1))->getNumberFormat()->setFormatCode('$#,##0');
            }
        }
    }

    private function operations(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Operaciones Kizeo');
        $headers = ['Kizeo', 'Fecha operativa', 'Tipo', 'Persona', 'RUT', 'Centro de costo imputado', 'Entregado', 'Devuelto', 'Neto', 'Valor neto (CLP)', 'Líneas'];
        $sheet->fromArray($headers, null, 'A1');
        $this->heading($sheet, 'A1:K1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:K1');

        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->entrega->kizeo_record_number ?: $record->entrega->kizeo_data_id,
                $record->fecha ? \Carbon\Carbon::parse($record->fecha)->format('d/m/Y') : null,
                $record->tipo,
                $record->persona,
                $record->rut,
                $record->centro,
                $record->entregadas,
                $record->devueltas,
                $record->netas,
                $record->valor_neto,
                $record->items->count(),
            ], null, "A{$row}");
            $this->zebra($sheet, $row, 'K');
            $row++;
        }
        if ($row > 2) {
            $sheet->getStyle('G2:I'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.###');
            $sheet->getStyle('J2:J'.($row - 1))->getNumberFormat()->setFormatCode('$#,##0');
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['D', 'F'], true) ? 31 : 18);
        }
    }

    private function items(Spreadsheet $book, Collection $records): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Detalle EPP');
        $headers = ['Kizeo', 'Fecha operativa', 'Tipo', 'Persona', 'Centro de costo imputado', 'Artículo EPP', 'Talla', 'Entregado', 'Devuelto', 'Neto', 'Precio referencia (CLP)', 'Valor neto (CLP)', 'Origen del precio'];
        $sheet->fromArray($headers, null, 'A1');
        $this->heading($sheet, 'A1:M1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M1');

        $row = 2;
        foreach ($records as $record) {
            foreach ($record->items as $item) {
                $sheet->fromArray([
                    $record->entrega->kizeo_record_number ?: $record->entrega->kizeo_data_id,
                    $record->fecha ? \Carbon\Carbon::parse($record->fecha)->format('d/m/Y') : null,
                    $record->tipo,
                    $record->persona,
                    $record->centro,
                    $item->articulo,
                    $item->talla,
                    $item->entregadas,
                    $item->devueltas,
                    $item->netas,
                    $item->precio_referencia,
                    $item->valor_neto,
                    $item->origen_precio ?: 'Sin precio de referencia',
                ], null, "A{$row}");
                $this->zebra($sheet, $row, 'M');
                $row++;
            }
        }
        if ($row > 2) {
            $sheet->getStyle('H2:J'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.###');
            $sheet->getStyle('K2:L'.($row - 1))->getNumberFormat()->setFormatCode('$#,##0');
        }
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['D', 'E', 'F', 'M'], true) ? 31 : 18);
        }
    }

    private function title($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.self::PURPLE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function heading($sheet, string $range, string $color = self::ORANGE): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    private function zebra($sheet, int $row, string $lastColumn): void
    {
        if ($row % 2 === 0) {
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.self::LIGHT);
        }
    }
}
