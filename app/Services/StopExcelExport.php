<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StopExcelExport
{
    private Spreadsheet $spreadsheet;
    private string $periodo;
    private array $comparison;

    // CCU brand colors
    private const CCU_GREEN = '1B5E20';
    private const RED       = '991B1B';
    private const GREEN     = '16A34A';
    private const GRAY_BG   = 'F1F5F9';
    private const GRAY_HDR  = 'E2E8F0';
    private const WHITE     = 'FFFFFF';

    public function generate(array $analytics, string $periodo, string $frecuencia = 'Semanal', array $comparison = []): string
    {
        $this->periodo    = $periodo;
        $this->frecuencia = $frecuencia;
        $this->comparison = $comparison;
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getProperties()
            ->setCreator('SAEP')
            ->setTitle("Reporte {$frecuencia} Tarjeta STOP — {$periodo}")
            ->setSubject('Auditorías STOP');

        $this->buildResumen($analytics);
        $this->buildCentros($analytics);
        $this->buildAreas($analytics);
        $this->buildObservadores($analytics);
        $this->buildEmpresas($analytics);
        $this->buildTrabajadores($analytics);
        $this->buildTiposObservacion($analytics);
        $this->buildTendencia($analytics);
        $this->buildDetalleExtra($analytics);
        if (!empty($this->comparison)) {
            $this->buildComparativa($analytics);
        }

        // Set Resumen as the active sheet
        $this->spreadsheet->setActiveSheetIndex(0);

        $path = storage_path('app/stop_reporte_' . now()->format('Ymd_His') . '.xlsx');
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);

        return $path;
    }

    /* ── Resumen General ──────────────────────────────────────── */
    private function buildResumen(array $a): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        $total = $a['totalRows'] ?? 0;
        $clasif = $a['clasificacion'] ?? [];
        $pos = $clasif['Positiva'] ?? $clasif['positiva'] ?? 0;
        $neg = $clasif['Negativa'] ?? $clasif['negativa'] ?? 0;
        $pctPos = $total > 0 ? $pos / $total : 0;
        $pctNeg = $total > 0 ? $neg / $total : 0;

        // Header
        $weekTag = strtolower($this->frecuencia) === 'semanal'
            ? ' — Semana ' . now()->subWeek()->isoFormat('W')
            : '';
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', "AUDITORÍAS STOP — Reporte {$this->frecuencia}{$weekTag}");
        $this->styleHeader($sheet, 'A1:F1');
        $sheet->getRowDimension(1)->setRowHeight(36);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', "Período: {$this->periodo}");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // KPI cards row
        $row = 4;
        $kpis = [
            ['Total Tarjetas', $total, self::CCU_GREEN],
            ['Positivas', $pos, self::GREEN],
            ['Negativas', $neg, self::RED],
        ];

        foreach ($kpis as $i => [$label, $value, $color]) {
            $col = chr(65 + ($i * 2)); // A, C, E
            $col2 = chr(66 + ($i * 2)); // B, D, F
            $sheet->mergeCells("{$col}{$row}:{$col2}{$row}");
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}:{$col2}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::WHITE], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $vRow = $row + 1;
            $sheet->mergeCells("{$col}{$vRow}:{$col2}{$vRow}");
            $sheet->setCellValue("{$col}{$vRow}", $value);
            $sheet->getStyle("{$col}{$vRow}:{$col2}{$vRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 18],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $color]]],
            ]);
        }

        // Percentages
        $row = 7;
        $sheet->setCellValue("A{$row}", 'Tasa Positivas');
        $sheet->setCellValue("B{$row}", $pctPos);
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true)->getColor()->setARGB(self::GREEN);

        $sheet->setCellValue("C{$row}", 'Tasa Negativas');
        $sheet->setCellValue("D{$row}", $pctNeg);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
        $sheet->getStyle("D{$row}")->getFont()->setBold(true)->getColor()->setARGB(self::RED);

        $sheet->setCellValue("E{$row}", 'Meta Positivas');
        $sheet->setCellValue("F{$row}", 0.60);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setSize(10);

        // Summary stats
        $row = 9;
        $summaryData = [
            ['Centros de Trabajo', count($a['centros'] ?? [])],
            ['Observadores Únicos', count($a['topObservadores'] ?? [])],
            ['Áreas/Zonas', count($a['areas'] ?? [])],
            ['Empresas Observadas', count($a['empresas'] ?? [])],
        ];
        foreach ($summaryData as [$label, $val]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $val);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;
        }

        // Interno vs Externo
        $intExt = $a['internoExterno'] ?? [];
        if (!empty($intExt)) {
            $row += 1;
            $sheet->setCellValue("A{$row}", 'Interno vs Externo');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $row++;
            foreach ($intExt as $tipo => $cnt) {
                $sheet->setCellValue("A{$row}", $tipo);
                $sheet->setCellValue("B{$row}", $cnt);
                $row++;
            }
        }

        $this->autoWidth($sheet, 'A', 'F');
    }

    /* ── Centros de Trabajo ───────────────────────────────────── */
    private function buildCentros(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Centros');

        $centros    = $a['centros'] ?? [];
        $centrosNeg = $a['centrosNeg'] ?? [];
        $centrosPos = $a['centrosPos'] ?? [];

        $this->writeTitle($sheet, 'A1:E1', 'Tarjetas STOP por Centro de Trabajo');

        $headers = ['Centro', 'Total', 'Negativas', 'Positivas', '% Positivas'];
        $this->writeTableHeader($sheet, 2, $headers);

        $row = 3;
        foreach ($centros as $name => $total) {
            $n = $centrosNeg[$name] ?? 0;
            $p = $centrosPos[$name] ?? 0;
            $pct = $total > 0 ? $p / $total : 0;

            $sheet->setCellValue("A{$row}", $name);
            $sheet->setCellValue("B{$row}", $total);
            $sheet->setCellValue("C{$row}", $n);
            $sheet->setCellValue("D{$row}", $p);
            $sheet->setCellValue("E{$row}", $pct);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

            $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::RED);
            $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB(self::GREEN);
            $this->stripeRow($sheet, "A{$row}:E{$row}", $row);
            $row++;
        }

        $this->addTableBorder($sheet, "A2:E" . ($row - 1));
        $this->autoWidth($sheet, 'A', 'E');
    }

    /* ── Áreas / Zonas ────────────────────────────────────────── */
    private function buildAreas(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Áreas');

        $areas    = $a['areas'] ?? [];
        $areasNeg = $a['areasNeg'] ?? [];
        $areasPos = $a['areasPos'] ?? [];

        $this->writeTitle($sheet, 'A1:E1', 'Tarjetas STOP por Área o Proceso');

        $headers = ['Área / Zona', 'Total', 'Negativas', 'Positivas', '% Positivas'];
        $this->writeTableHeader($sheet, 2, $headers);

        $row = 3;
        foreach ($areas as $name => $total) {
            $n = $areasNeg[$name] ?? 0;
            $p = $areasPos[$name] ?? 0;
            $pct = $total > 0 ? $p / $total : 0;

            $sheet->setCellValue("A{$row}", $name);
            $sheet->setCellValue("B{$row}", $total);
            $sheet->setCellValue("C{$row}", $n);
            $sheet->setCellValue("D{$row}", $p);
            $sheet->setCellValue("E{$row}", $pct);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

            $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::RED);
            $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB(self::GREEN);
            $this->stripeRow($sheet, "A{$row}:E{$row}", $row);
            $row++;
        }

        $this->addTableBorder($sheet, "A2:E" . ($row - 1));
        $this->autoWidth($sheet, 'A', 'E');
    }

    /* ── Top Observadores ─────────────────────────────────────── */
    private function buildObservadores(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Observadores');

        $topObs = $a['topObservadores'] ?? [];
        $obsNeg = $a['observadoresNeg'] ?? [];
        $obsPos = $a['observadoresPos'] ?? [];

        $this->writeTitle($sheet, 'A1:F1', 'Total de Tarjetas por Observador');

        $headers = ['#', 'Observador', 'Total', 'Negativas', 'Positivas', '% Positivas'];
        $this->writeTableHeader($sheet, 2, $headers);

        $row = 3;
        $rank = 1;
        foreach ($topObs as $name => $total) {
            $n = $obsNeg[$name] ?? 0;
            $p = $obsPos[$name] ?? 0;
            $pct = $total > 0 ? $p / $total : 0;

            $sheet->setCellValue("A{$row}", $rank);
            $sheet->setCellValue("B{$row}", mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'));
            $sheet->setCellValue("C{$row}", $total);
            $sheet->setCellValue("D{$row}", $n);
            $sheet->setCellValue("E{$row}", $p);
            $sheet->setCellValue("F{$row}", $pct);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);

            $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB(self::RED);
            $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB(self::GREEN);

            if ($rank <= 3) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB('F59E0B');
            }

            $this->stripeRow($sheet, "A{$row}:F{$row}", $row);
            $rank++;
            $row++;
        }

        $this->addTableBorder($sheet, "A2:F" . ($row - 1));
        $this->autoWidth($sheet, 'A', 'F');
    }

    /* ── Empresas ─────────────────────────────────────────────── */
    private function buildEmpresas(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Empresas');

        // Empresa del observado
        $empresas    = $a['empresas'] ?? [];
        $empresasNeg = $a['empresasNeg'] ?? [];
        $empresasPos = $a['empresasPos'] ?? [];

        $this->writeTitle($sheet, 'A1:E1', 'Tarjetas STOP por Empresa (Observado)');

        $headers = ['Empresa', 'Total', 'Negativas', 'Positivas', '% Positivas'];
        $this->writeTableHeader($sheet, 2, $headers);

        $row = 3;
        foreach ($empresas as $name => $total) {
            $n = $empresasNeg[$name] ?? 0;
            $p = $empresasPos[$name] ?? 0;
            $pct = $total > 0 ? $p / $total : 0;

            $sheet->setCellValue("A{$row}", $name);
            $sheet->setCellValue("B{$row}", $total);
            $sheet->setCellValue("C{$row}", $n);
            $sheet->setCellValue("D{$row}", $p);
            $sheet->setCellValue("E{$row}", $pct);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
            $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::RED);
            $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB(self::GREEN);
            $this->stripeRow($sheet, "A{$row}:E{$row}", $row);
            $row++;
        }

        $this->addTableBorder($sheet, "A2:E" . ($row - 1));

        // Empresa del observador
        $empresasObs = $a['empresasObservador'] ?? [];
        if (!empty($empresasObs)) {
            $row += 2;
            $titleRow = $row;
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("A{$row}", 'Empresa del Observador (Quien pasó la tarjeta)');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::WHITE], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CCU_GREEN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(28);

            $row++;
            $this->writeTableHeader($sheet, $row, ['Empresa', 'Total'], 'A');
            $row++;
            foreach ($empresasObs as $name => $total) {
                $sheet->setCellValue("A{$row}", $name);
                $sheet->setCellValue("B{$row}", $total);
                $this->stripeRow($sheet, "A{$row}:B{$row}", $row);
                $row++;
            }
            $this->addTableBorder($sheet, "A{$titleRow}:B" . ($row - 1));
        }

        $this->autoWidth($sheet, 'A', 'E');
    }

    /* ── Trabajadores Neg/Pos ─────────────────────────────────── */
    private function buildTrabajadores(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Trabajadores');

        // Top Negativos
        $topNeg = $a['topNegTrabajadores'] ?? [];
        $this->writeTitle($sheet, 'A1:C1', 'Trabajadores con Mayor Tarjetas STOP Negativas');

        $headers = ['#', 'Trabajador', 'Negativas'];
        $this->writeTableHeader($sheet, 2, $headers);

        $row = 3;
        $rank = 1;
        foreach ($topNeg as $name => $cnt) {
            $sheet->setCellValue("A{$row}", $rank);
            $sheet->setCellValue("B{$row}", mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'));
            $sheet->setCellValue("C{$row}", $cnt);
            $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::RED);
            $this->stripeRow($sheet, "A{$row}:C{$row}", $row);
            $rank++;
            $row++;
        }
        $this->addTableBorder($sheet, "A2:C" . ($row - 1));

        // Top Positivos
        $topPos = $a['topPosTrabajadores'] ?? [];
        if (!empty($topPos)) {
            $row += 2;
            $startRow = $row;
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("A{$row}", 'Trabajadores con Tarjetas STOP Positivas');
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::WHITE], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::GREEN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(28);

            $row++;
            $this->writeTableHeader($sheet, $row, ['#', 'Trabajador', 'Positivas']);
            $row++;
            $rank = 1;
            foreach ($topPos as $name => $cnt) {
                $sheet->setCellValue("A{$row}", $rank);
                $sheet->setCellValue("B{$row}", mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'));
                $sheet->setCellValue("C{$row}", $cnt);
                $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::GREEN);
                $this->stripeRow($sheet, "A{$row}:C{$row}", $row);
                $rank++;
                $row++;
            }
            $this->addTableBorder($sheet, "A{$startRow}:C" . ($row - 1));
        }

        $this->autoWidth($sheet, 'A', 'C');
    }

    /* ── Tipos de Observación ─────────────────────────────────── */
    private function buildTiposObservacion(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Tipos Observación');

        $negPorTipo = $a['negPorTipo'] ?? [];
        $posPorTipo = $a['posPorTipo'] ?? [];

        $this->writeTitle($sheet, 'A1:C1', 'Principal Razón — Tarjetas Negativas');

        $this->writeTableHeader($sheet, 2, ['Tipo de Observación', 'Negativas', 'Positivas']);

        // Combine all types
        $allTypes = array_unique(array_merge(array_keys($negPorTipo), array_keys($posPorTipo)));

        $row = 3;
        foreach ($allTypes as $type) {
            $n = $negPorTipo[$type] ?? 0;
            $p = $posPorTipo[$type] ?? 0;

            $sheet->setCellValue("A{$row}", $type);
            $sheet->setCellValue("B{$row}", $n);
            $sheet->setCellValue("C{$row}", $p);
            $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB(self::RED);
            $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::GREEN);
            $this->stripeRow($sheet, "A{$row}:C{$row}", $row);
            $row++;
        }

        $this->addTableBorder($sheet, "A2:C" . ($row - 1));
        $this->autoWidth($sheet, 'A', 'C');
    }

    /* ── Tendencia Mensual ────────────────────────────────────── */
    private function buildTendencia(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Tendencia');

        $byMonth    = $a['byMonth'] ?? [];
        $byMonthNeg = $a['byMonthNeg'] ?? [];
        $byMonthPos = $a['byMonthPos'] ?? [];

        $this->writeTitle($sheet, 'A1:D1', 'Tendencia Mensual — Tarjetas STOP');

        $this->writeTableHeader($sheet, 2, ['Mes', 'Total', 'Negativas', 'Positivas']);

        $row = 3;
        foreach ($byMonth as $month => $total) {
            $n = $byMonthNeg[$month] ?? 0;
            $p = $byMonthPos[$month] ?? 0;

            $sheet->setCellValue("A{$row}", $month);
            $sheet->setCellValue("B{$row}", $total);
            $sheet->setCellValue("C{$row}", $n);
            $sheet->setCellValue("D{$row}", $p);
            $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::RED);
            $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB(self::GREEN);
            $this->stripeRow($sheet, "A{$row}:D{$row}", $row);
            $row++;
        }

        $this->addTableBorder($sheet, "A2:D" . ($row - 1));

        // Year totals
        $byYear = $a['byYear'] ?? [];
        if (!empty($byYear)) {
            $row += 2;
            $sheet->setCellValue("A{$row}", 'Total por Año');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $row++;
            $this->writeTableHeader($sheet, $row, ['Año', 'Total']);
            $row++;
            foreach ($byYear as $y => $cnt) {
                $sheet->setCellValue("A{$row}", $y);
                $sheet->setCellValue("B{$row}", $cnt);
                $row++;
            }
        }

        $this->autoWidth($sheet, 'A', 'D');
    }

    /* ── Detalle Extra (Turno, Antigüedad, Cargo) ─────────────── */
    private function buildDetalleExtra(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Detalle');

        $this->writeTitle($sheet, 'A1:B1', 'Distribución por Turno');

        $turnos = $a['turnos'] ?? [];
        $this->writeTableHeader($sheet, 2, ['Turno', 'Cantidad']);
        $row = 3;
        foreach ($turnos as $name => $cnt) {
            $sheet->setCellValue("A{$row}", $name);
            $sheet->setCellValue("B{$row}", $cnt);
            $this->stripeRow($sheet, "A{$row}:B{$row}", $row);
            $row++;
        }
        $this->addTableBorder($sheet, "A2:B" . ($row - 1));

        // Antigüedad
        $antiguedades = $a['antiguedades'] ?? [];
        if (!empty($antiguedades)) {
            $row += 2;
            $startRow = $row;
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", 'Distribución por Antigüedad');
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::WHITE], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CCU_GREEN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(28);
            $row++;
            $this->writeTableHeader($sheet, $row, ['Antigüedad', 'Cantidad']);
            $row++;
            foreach ($antiguedades as $name => $cnt) {
                $sheet->setCellValue("A{$row}", $name);
                $sheet->setCellValue("B{$row}", $cnt);
                $this->stripeRow($sheet, "A{$row}:B{$row}", $row);
                $row++;
            }
            $this->addTableBorder($sheet, "A{$startRow}:B" . ($row - 1));
        }

        // Cargos
        $cargos = $a['cargos'] ?? [];
        if (!empty($cargos)) {
            $row += 2;
            $startRow = $row;
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", 'Distribución por Cargo');
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::WHITE], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CCU_GREEN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(28);
            $row++;
            $this->writeTableHeader($sheet, $row, ['Cargo', 'Cantidad']);
            $row++;
            foreach ($cargos as $name => $cnt) {
                $sheet->setCellValue("A{$row}", $name);
                $sheet->setCellValue("B{$row}", $cnt);
                $this->stripeRow($sheet, "A{$row}:B{$row}", $row);
                $row++;
            }
            $this->addTableBorder($sheet, "A{$startRow}:B" . ($row - 1));
        }

        $this->autoWidth($sheet, 'A', 'B');
    }

    /* ── Comparativa Año Anterior ─────────────────────────────── */
    private function buildComparativa(array $a): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Comparativa');

        $ytd  = $this->comparison['ytd'] ?? [];
        $prev = $this->comparison['prevYear'] ?? [];
        $prevYear = $prev['year'] ?? ((int) date('Y') - 1);
        $currYear = date('Y');

        $total = $a['totalRows'] ?? 0;
        $clasif = $a['clasificacion'] ?? [];
        $pos = $clasif['Positiva'] ?? $clasif['positiva'] ?? 0;
        $neg = $clasif['Negativa'] ?? $clasif['negativa'] ?? 0;

        // Header
        $weekTag = strtolower($this->frecuencia) === 'semanal'
            ? ' — Semana ' . now()->subWeek()->isoFormat('W')
            : '';
        $this->writeTitle($sheet, 'A1:G1', "Comparativa vs {$prevYear} — Reporte {$this->frecuencia}{$weekTag} — {$this->periodo}");

        // --- Resumen comparativo ---
        $row = 3;
        $sheet->setCellValue("A{$row}", 'Métrica');
        $sheet->setCellValue("B{$row}", 'Periodo Actual');
        $sheet->setCellValue("C{$row}", "Mismo Mes {$prevYear}");
        $sheet->setCellValue("D{$row}", 'Variación');
        $sheet->setCellValue("E{$row}", "Acum. {$currYear}");
        $sheet->setCellValue("F{$row}", "Acum. {$prevYear} (mismo corte)");
        $sheet->setCellValue("G{$row}", 'Var. Acum.');
        $this->styleHeader($sheet, "A{$row}:G{$row}");

        $dataRows = [
            ['Total Tarjetas', $total, $prev['sameMonthTotal'] ?? 0, $ytd['total'] ?? 0, $prev['ytdTotal'] ?? 0],
            ['Negativas', $neg, $prev['sameMonthNeg'] ?? 0, $ytd['neg'] ?? 0, $prev['ytdNeg'] ?? 0],
            ['Positivas', $pos, $prev['sameMonthPos'] ?? 0, $ytd['pos'] ?? 0, $prev['ytdPos'] ?? 0],
        ];
        $row++;
        foreach ($dataRows as $d) {
            $delta = $d[1] - $d[2];
            $deltaYtd = $d[3] - $d[4];
            $sheet->setCellValue("A{$row}", $d[0]);
            $sheet->setCellValue("B{$row}", $d[1]);
            $sheet->setCellValue("C{$row}", $d[2]);
            $sheet->setCellValue("D{$row}", ($delta >= 0 ? '+' : '') . $delta);
            $sheet->setCellValue("E{$row}", $d[3]);
            $sheet->setCellValue("F{$row}", $d[4]);
            $sheet->setCellValue("G{$row}", ($deltaYtd >= 0 ? '+' : '') . $deltaYtd);

            // Color the delta
            $color = $delta > 0 ? self::RED : ($delta < 0 ? self::GREEN : '64748B');
            $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB("FF{$color}");
            $colorYtd = $deltaYtd > 0 ? self::RED : ($deltaYtd < 0 ? self::GREEN : '64748B');
            $sheet->getStyle("G{$row}")->getFont()->getColor()->setARGB("FF{$colorYtd}");

            $this->stripeRow($sheet, "A{$row}:G{$row}", $row);
            $row++;
        }
        $this->addTableBorder($sheet, 'A3:G' . ($row - 1));

        // --- Tendencia mensual comparativa ---
        $row += 2;
        $this->writeTitle($sheet, "A{$row}:G{$row}", "Tendencia Mensual — {$currYear} vs {$prevYear}");
        $row++;
        $sheet->setCellValue("A{$row}", 'Mes');
        $sheet->setCellValue("B{$row}", "{$currYear} Total");
        $sheet->setCellValue("C{$row}", "{$currYear} Neg");
        $sheet->setCellValue("D{$row}", "{$currYear} Pos");
        $sheet->setCellValue("E{$row}", "{$prevYear} Total");
        $sheet->setCellValue("F{$row}", "{$prevYear} Neg");
        $sheet->setCellValue("G{$row}", "{$prevYear} Pos");
        $this->styleHeader($sheet, "A{$row}:G{$row}");

        $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
        $startRow = $row + 1;
        $row++;
        $ytdByMonth    = $ytd['byMonth'] ?? [];
        $ytdByMonthNeg = $ytd['byMonthNeg'] ?? [];
        $ytdByMonthPos = $ytd['byMonthPos'] ?? [];
        $prevByMonth    = $prev['byMonth'] ?? [];
        $prevByMonthNeg = $prev['byMonthNeg'] ?? [];
        $prevByMonthPos = $prev['byMonthPos'] ?? [];

        foreach ($meses as $m => $name) {
            $curKey = "{$currYear}-{$m}";
            $prvKey = "{$prevYear}-{$m}";
            $cT = $ytdByMonth[$curKey] ?? 0;
            $pT = $prevByMonth[$prvKey] ?? 0;
            if ($cT === 0 && $pT === 0) continue;

            $sheet->setCellValue("A{$row}", $name);
            $sheet->setCellValue("B{$row}", $cT);
            $sheet->setCellValue("C{$row}", $ytdByMonthNeg[$curKey] ?? 0);
            $sheet->setCellValue("D{$row}", $ytdByMonthPos[$curKey] ?? 0);
            $sheet->setCellValue("E{$row}", $pT);
            $sheet->setCellValue("F{$row}", $prevByMonthNeg[$prvKey] ?? 0);
            $sheet->setCellValue("G{$row}", $prevByMonthPos[$prvKey] ?? 0);
            $this->stripeRow($sheet, "A{$row}:G{$row}", $row);
            $row++;
        }
        // Total row
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("B{$row}", $ytd['total'] ?? 0);
        $sheet->setCellValue("C{$row}", $ytd['neg'] ?? 0);
        $sheet->setCellValue("D{$row}", $ytd['pos'] ?? 0);
        $sheet->setCellValue("E{$row}", $prev['total'] ?? 0);
        $sheet->setCellValue("F{$row}", $prev['neg'] ?? 0);
        $sheet->setCellValue("G{$row}", $prev['pos'] ?? 0);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->getStartColor()->setARGB('FF' . self::CCU_GREEN);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->getColor()->setARGB('FF' . self::WHITE);
        if ($startRow <= $row) {
            $this->addTableBorder($sheet, "A{$startRow}:G{$row}");
        }

        // --- Top Negativos YTD ---
        if (!empty($ytd['topNeg'])) {
            $row += 2;
            $this->writeTitle($sheet, "A{$row}:C{$row}", "Top Trabajadores Negativos — Acumulado {$currYear}");
            $row++;
            $sheet->setCellValue("A{$row}", '#');
            $sheet->setCellValue("B{$row}", 'Trabajador');
            $sheet->setCellValue("C{$row}", 'Tarjetas Neg.');
            $this->styleHeader($sheet, "A{$row}:C{$row}");
            $startRow = $row + 1;
            $row++;
            $i = 1;
            foreach ($ytd['topNeg'] as $nombre => $cnt) {
                $sheet->setCellValue("A{$row}", $i++);
                $sheet->setCellValue("B{$row}", $nombre);
                $sheet->setCellValue("C{$row}", $cnt);
                $this->stripeRow($sheet, "A{$row}:C{$row}", $row);
                $row++;
            }
            $this->addTableBorder($sheet, "A{$startRow}:C" . ($row - 1));
        }

        // --- Top Tipos Falta Negativa YTD ---
        if (!empty($ytd['negPorTipo'])) {
            $row += 2;
            $this->writeTitle($sheet, "A{$row}:C{$row}", "Tipos de Falta Negativa — Acumulado {$currYear}");
            $row++;
            $sheet->setCellValue("A{$row}", '#');
            $sheet->setCellValue("B{$row}", 'Tipo de Falta');
            $sheet->setCellValue("C{$row}", 'Cantidad');
            $this->styleHeader($sheet, "A{$row}:C{$row}");
            $startRow = $row + 1;
            $row++;
            $i = 1;
            foreach ($ytd['negPorTipo'] as $tipo => $cnt) {
                $sheet->setCellValue("A{$row}", $i++);
                $sheet->setCellValue("B{$row}", $tipo);
                $sheet->setCellValue("C{$row}", $cnt);
                $this->stripeRow($sheet, "A{$row}:C{$row}", $row);
                $row++;
            }
            $this->addTableBorder($sheet, "A{$startRow}:C" . ($row - 1));
        }

        $this->autoWidth($sheet, 'A', 'G');
    }

    /* ━━━━━━ Styling helpers ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::WHITE], 'size' => 14],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CCU_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function writeTitle($sheet, string $range, string $title): void
    {
        $sheet->mergeCells($range);
        $sheet->setCellValue(explode(':', $range)[0], $title);
        $this->styleHeader($sheet, $range);
        $row = (int) preg_replace('/[^0-9]/', '', explode(':', $range)[0]);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    private function writeTableHeader($sheet, int $row, array $headers, string $startCol = 'A'): void
    {
        $col = $startCol;
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}{$row}", $h);
            $col++;
        }
        $endCol = chr(ord($startCol) + count($headers) - 1);
        $sheet->getStyle("{$startCol}{$row}:{$endCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::GRAY_HDR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '94A3B8']],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function stripeRow($sheet, string $range, int $row): void
    {
        if ($row % 2 === 0) {
            $sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::GRAY_BG);
        }
    }

    private function addTableBorder($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'CBD5E1']],
                'inside' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'E2E8F0']],
            ],
        ]);
    }

    private function autoWidth($sheet, string $from, string $to): void
    {
        for ($col = $from; $col <= $to; $col++) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
