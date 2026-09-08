<?php

namespace App\Modules\Comercial\Services;

use App\Modules\Comercial\Models\CentroCosto;
use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\Modalidad;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Imports legacy quotation spreadsheets as immutable commercial snapshots.
 *
 * The legacy workbooks have many formats and often use business rules that no
 * longer match the active calculator. This service deliberately never invokes
 * CalculadoraCotizacionService: it preserves the source's cached values.
 */
class ImportadorHistoricoCotizacionesService
{
    private const MAX_SCAN_ROWS = 1800;

    private const MAX_SCAN_COLUMNS = 32;

    /** @var array<string, string> */
    private const CLIENT_ALIASES = [
        'WALMART' => 'Walmart Chile',
        'LTS ' => 'Walmart Chile',
        'DHL' => 'DHL',
        'MAERSK' => 'Maersk',
        'CCU' => 'CCU',
        'SODIMAC' => 'Sodimac',
        'UNIMARC' => 'Unimarc',
        'TOTTUS' => 'Tottus',
        'LA POLAR' => 'La Polar',
        'PULMAHUE' => 'Pulmahue',
        'SKECHERS' => 'Skechers',
        'SELECTA' => 'Selecta',
        'GRUPO AXO' => 'Grupo Axo',
        'HAMBURGO' => 'Hamburgo',
        'AMERINODE' => 'Amerinode',
        'BETTER COMMERCE' => 'Better Commerce',
        'AGROSUPER' => 'Agrosuper',
        'COMERCIAL MK' => 'Comercial MK',
        'COMERCIAL SAAB' => 'Comercial SAAB',
        'IMOLOG' => 'Imolog',
        'LAF' => 'LAF',
        'MEDTRONIC' => 'Medtronic',
        'MM GROUP' => 'MM Group',
        'IDL' => 'IDL',
        'SAEP' => 'SAEP (Interno)',
    ];

    /** @var list<string> */
    private const CENTER_STOP_WORDS = ['TARIFAS', 'TARIFA', 'COTIZACION', 'COTIZACIONES', 'FORMATO', 'REFORMA', 'PREVISIONAL', 'FINAL', 'VIGENTE'];

    /**
     * @return array{status:string, reason?:string, records?:array<int, array<string, mixed>>, source?:array<string, mixed>}
     */
    public function analizar(string $path, string $baseDirectory): array
    {
        if (! is_file($path) || ! Str::endsWith(Str::lower($path), '.xlsx')) {
            return ['status' => 'omitido', 'reason' => 'No es un archivo XLSX legible.'];
        }

        $relativePath = ltrim(str_replace('\\', '/', Str::after($path, rtrim($baseDirectory, "\\/"))), '/');
        $source = [
            'tipo' => 'archivo_historico',
            'archivo' => basename($path),
            'ruta_relativa' => $relativePath,
            'hash_sha256' => hash_file('sha256', $path),
            'fecha_archivo' => CarbonImmutable::createFromTimestamp((int) filemtime($path))->toDateString(),
            'tamano_bytes' => filesize($path),
        ];

        if (str_contains($this->normalizar(basename($path)), 'NO USAR')) {
            return ['status' => 'omitido', 'reason' => 'El nombre de origen indica NO USAR.', 'source' => $source];
        }

        $modalidad = $this->resolverModalidad($relativePath);
        if ($modalidad === null) {
            return ['status' => 'requiere_revision', 'reason' => 'No se pudo determinar una única modalidad EST o SUB.', 'source' => $source];
        }

        $cliente = $this->resolverCliente($relativePath);
        if ($cliente === null) {
            return ['status' => 'requiere_revision', 'reason' => 'Cliente no homologado en maestros comerciales.', 'source' => $source];
        }

        $centro = $this->resolverCentroCosto($cliente, $relativePath, $modalidad);
        if ($centro === null) {
            return ['status' => 'requiere_revision', 'reason' => 'Centro de costo no identificado con seguridad.', 'source' => $source];
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $exception) {
            return ['status' => 'requiere_revision', 'reason' => 'No fue posible abrir el archivo: '.$exception->getMessage(), 'source' => $source];
        }

        try {
            $formulaErrors = $this->buscarErroresFormula($spreadsheet->getAllSheets());
            if ($formulaErrors !== []) {
                return [
                    'status' => 'observado',
                    'reason' => 'El origen contiene errores de fórmula: '.implode(', ', $formulaErrors),
                    'source' => array_merge($source, ['errores_formula' => $formulaErrors]),
                ];
            }

            $records = [];
            foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
                foreach ($this->extraerBloquesPrecio($sheet) as $block) {
                    $fecha = $this->resolverFechaBloque($sheet, $block['row'], $path)
                        ?? $this->resolverFechaArchivo($path);

                    if ($fecha === null) {
                        continue;
                    }

                    $cargo = $this->resolverCargo($sheet, $block['row']);
                    if ($cargo === null) {
                        continue;
                    }

                    $records[] = $this->construirRegistro(
                        $source,
                        $sheet,
                        $sheetIndex,
                        $block,
                        $fecha,
                        $cargo,
                        $cliente,
                        $centro,
                        $modalidad,
                    );
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        if ($records === []) {
            return ['status' => 'requiere_revision', 'reason' => 'No se identificó una tarifa completa con precio y cargo.', 'source' => $source];
        }

        return ['status' => 'listo', 'records' => $records, 'source' => $source];
    }

    /** @param array<string, mixed> $record */
    public function importar(array $record): Cotizacion
    {
        return DB::transaction(function () use ($record): Cotizacion {
            $existing = Cotizacion::withTrashed()->where('numero', $record['numero'])->first();
            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                return $existing;
            }

            /** @var Cotizacion $cotizacion */
            $cotizacion = Cotizacion::create([
                'numero' => $record['numero'],
                'titulo' => $record['titulo'],
                'cargo' => $record['cargo'],
                'cliente_id' => $record['cliente_id'],
                'centro_costo_id' => $record['centro_costo_id'],
                'modalidad_id' => $record['modalidad_id'],
                'usuario_id' => null,
                'estado' => Cotizacion::ESTADO_NO_VIGENTE,
                'version' => 1,
                'fecha_cotizacion' => $record['fecha_cotizacion'],
                'fecha_vigencia_desde' => $record['fecha_cotizacion'],
                'fecha_vigencia_hasta' => $record['fecha_cotizacion'],
                'fecha_fin_vigencia_real' => $record['fecha_cotizacion']->endOfDay(),
                'observaciones' => $record['observaciones'],
                'total_remuneraciones' => $record['totales']['remuneraciones'],
                'total_cotizaciones' => $record['totales']['cotizaciones'],
                'total_provisiones' => $record['totales']['provisiones'],
                'total_gastos' => $record['totales']['gastos'],
                'subtotal' => $record['totales']['subtotal'],
                'margen' => $record['totales']['margen'],
                'precio_venta' => $record['totales']['precio_venta'],
                'datos_calculo' => $record['datos_calculo'],
                'detalles_json' => $record['detalles'],
            ]);

            foreach ($record['detalles'] as $detalle) {
                $cotizacion->detalles()->create($detalle);
            }

            $cotizacion->auditorias()->create([
                'usuario_id' => null,
                'accion' => 'importada_historico',
                'descripcion' => 'Cotización histórica importada desde archivo Excel, sin recalcular reglas vigentes.',
                'cambios' => [
                    'origen' => $record['datos_calculo']['origen'],
                    'fecha_origen' => $record['fecha_cotizacion']->toDateString(),
                    'precio_venta_origen' => $record['totales']['precio_venta'],
                ],
                'ip_address' => null,
                'user_agent' => 'comercial:importar-historico',
            ]);

            return $cotizacion;
        });
    }

    private function resolverModalidad(string $context): ?Modalidad
    {
        $normalized = ' '.$this->normalizar($context).' ';
        $hasEst = preg_match('/\\bEST\\b/', $normalized) === 1;
        $hasSub = preg_match('/\\bSUB\\b/', $normalized) === 1;

        if ($hasEst === $hasSub) {
            return null;
        }

        return Modalidad::where('codigo', $hasEst ? 'EST' : 'SUB')->first();
    }

    private function resolverCliente(string $context): ?Cliente
    {
        $normalized = ' '.$this->normalizar($context).' ';

        foreach (self::CLIENT_ALIASES as $alias => $nombre) {
            if (str_contains($normalized, ' '.$this->normalizar($alias).' ')) {
                return Cliente::query()->where('nombre', $nombre)->first();
            }
        }

        return null;
    }

    private function resolverCentroCosto(Cliente $cliente, string $context, Modalidad $modalidad): ?CentroCosto
    {
        $sourceTokens = $this->tokens($context);
        $matches = [];

        foreach ($cliente->centrosCosto()->where('estado', 'activo')->get() as $centro) {
            $centerTokens = array_values(array_filter(
                $this->tokens($centro->nombre),
                fn (string $token) => ! in_array($token, self::CENTER_STOP_WORDS, true),
            ));
            if (count($centerTokens) < 2 || array_diff($centerTokens, $sourceTokens) !== []) {
                continue;
            }

            $score = count($centerTokens) * 10;
            if (str_contains($this->normalizar($centro->nombre), $modalidad->codigo)) {
                $score += 4;
            }
            $matches[] = ['centro' => $centro, 'score' => $score];
        }

        usort($matches, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        if ($matches === [] || (isset($matches[1]) && $matches[0]['score'] === $matches[1]['score'])) {
            return null;
        }

        return $matches[0]['centro'];
    }

    /** @param list<Worksheet> $worksheets */
    private function buscarErroresFormula(array $worksheets): array
    {
        $errors = [];
        foreach ($worksheets as $sheet) {
            $maxRow = min($sheet->getHighestDataRow(), self::MAX_SCAN_ROWS);
            $maxColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_SCAN_COLUMNS);
            for ($row = 1; $row <= $maxRow; $row++) {
                for ($column = 1; $column <= $maxColumn; $column++) {
                    $value = $this->cellValue($sheet, $column, $row);
                    if (is_string($value) && preg_match('/^#(?:REF!|DIV\\/0!|VALUE!|NAME\\?|N\\/A|NUM!|NULL!)/', trim($value)) === 1) {
                        $errors[] = $sheet->getTitle().'!'.Coordinate::stringFromColumnIndex($column).$row.': '.$value;
                        if (count($errors) >= 5) {
                            return $errors;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /** @return list<array{row:int,column:int,price:float,coordinate:string}> */
    private function extraerBloquesPrecio(Worksheet $sheet): array
    {
        $blocks = [];
        $maxRow = min($sheet->getHighestDataRow(), self::MAX_SCAN_ROWS);
        $maxColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_SCAN_COLUMNS);

        for ($row = 1; $row <= $maxRow; $row++) {
            for ($column = 1; $column <= $maxColumn; $column++) {
                $label = $this->normalizar((string) $this->cellValue($sheet, $column, $row));
                if (! str_contains($label, 'PRECIO VENTA') || preg_match('/\\b(HORA|HHEE|EXTRA|DOMINGO|FESTIVO)\\b/', $label) === 1) {
                    continue;
                }

                $price = $this->numericValueToRight($sheet, $row, $column, min($column + 6, $maxColumn));
                if ($price === null || $price <= 0) {
                    continue;
                }

                $blocks[] = [
                    'row' => $row,
                    'column' => $column,
                    'price' => $price,
                    'coordinate' => Coordinate::stringFromColumnIndex($column).$row,
                ];
            }
        }

        return $blocks;
    }

    /** @return array{fecha:CarbonImmutable, fuente:string}|null */
    private function resolverFechaBloque(Worksheet $sheet, int $priceRow, string $path): ?array
    {
        $firstRow = max(1, $priceRow - 70);
        $maxColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_SCAN_COLUMNS);

        for ($row = $priceRow; $row >= $firstRow; $row--) {
            for ($column = 1; $column <= $maxColumn; $column++) {
                $value = $this->cellValue($sheet, $column, $row);
                if (! is_string($value)) {
                    continue;
                }

                $normalized = $this->normalizar($value);
                if (! str_contains($normalized, 'COTIZACION') && ! str_contains($normalized, 'EMISION')) {
                    continue;
                }

                if ($date = $this->parseDate($value)) {
                    return ['fecha' => $date, 'fuente' => 'contenido_cotizacion'];
                }

                for ($right = $column + 1; $right <= min($column + 4, $maxColumn); $right++) {
                    $candidate = $this->cell($sheet, $right, $row);
                    if ($date = $this->parseDate($candidate->getValue(), $candidate->getValue() !== null && ExcelDate::isDateTime($candidate))) {
                        return ['fecha' => $date, 'fuente' => 'celda_vecina_cotizacion'];
                    }
                }
            }
        }

        if ($date = $this->parseDate(pathinfo($path, PATHINFO_FILENAME))) {
            return ['fecha' => $date, 'fuente' => 'nombre_archivo'];
        }

        return null;
    }

    /** @return array{fecha:CarbonImmutable, fuente:string}|null */
    private function resolverFechaArchivo(string $path): ?array
    {
        $modified = filemtime($path);

        if ($modified === false) {
            return null;
        }

        return [
            'fecha' => CarbonImmutable::createFromTimestamp($modified)->startOfDay(),
            'fuente' => 'modificacion_archivo_referencial',
        ];
    }

    private function parseDate(mixed $value, bool $excelDate = false): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if ($excelDate && is_numeric($value)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        if (! is_string($value) || preg_match('/(?<!\\d)(\\d{1,2})[\\/-](\\d{1,2})[\\/-](\\d{2,4})(?!\\d)/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[3];
        $year += $year < 100 ? 2000 : 0;
        $day = (int) $matches[1];
        $month = (int) $matches[2];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    private function resolverCargo(Worksheet $sheet, int $priceRow): ?string
    {
        for ($row = $priceRow - 1; $row >= max(1, $priceRow - 55); $row--) {
            $values = $this->rowTextValues($sheet, $row, 8);
            foreach ($values as $text) {
                $normalized = $this->normalizar($text);
                if ($normalized === '' || str_contains($normalized, 'COTIZACION') || str_contains($normalized, 'PRECIO') || str_contains($normalized, 'TOTAL') || str_contains($normalized, 'SUELDO') || str_contains($normalized, 'REMUNERACION') || str_contains($normalized, 'COSTO')) {
                    continue;
                }
                if (preg_match('/\\b(OPERARI|OPERADOR|PICKING|BODEGA|DESPACH|ADMINISTR|ANALISTA|ENCARGADO|SUPERVIS|MOVILIZ|GRUA|ASISTENTE|CHOFER|AUXILIAR|LOGIST|COORDINADOR)\\w*/', $normalized) === 1) {
                    return Str::limit(trim($text), 180, '');
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     * @param array{row:int,column:int,price:float,coordinate:string} $block
     * @param array{fecha:CarbonImmutable, fuente:string} $fecha
     * @return array<string, mixed>
     */
    private function construirRegistro(array $source, Worksheet $sheet, int $sheetIndex, array $block, array $fecha, string $cargo, Cliente $cliente, CentroCosto $centro, Modalidad $modalidad): array
    {
        $details = $this->extraerDetalles($sheet, $block['row']);
        $totals = $this->resolverTotales($details, $block['price']);
        $source['hoja'] = $sheet->getTitle();
        $source['celda_precio'] = $block['coordinate'];
        $source['fecha_fuente'] = $fecha['fuente'];

        $year = $fecha['fecha']->year;
        $identifier = substr($source['hash_sha256'], 0, 10).'-'.($sheetIndex + 1).'-'.$block['row'];

        return [
            'numero' => "HIST-{$year}-{$identifier}",
            'titulo' => Str::limit('Histórico: '.pathinfo($source['archivo'], PATHINFO_FILENAME), 180, ''),
            'cargo' => $cargo,
            'cliente_id' => $cliente->id,
            'centro_costo_id' => $centro->id,
            'modalidad_id' => $modalidad->id,
            'fecha_cotizacion' => $fecha['fecha'],
            'observaciones' => 'Importada como fotografía histórica desde Excel. No usa ni modifica las reglas vigentes.',
            'resumen_importacion' => [
                'cliente' => $cliente->nombre,
                'centro' => $centro->nombre,
                'modalidad' => $modalidad->codigo,
                'fecha_fuente' => $fecha['fuente'],
            ],
            'totales' => $totals,
            'detalles' => $details,
            'datos_calculo' => [
                'origen' => $source,
                'es_fotografia_historica' => true,
                'margen_porcentaje' => $this->extraerMargenPorcentaje($details),
                'resumen_excel' => [
                    'totalHaberes' => $totals['remuneraciones'],
                    'costoBruto' => $totals['subtotal'],
                    'margen' => $totals['margen'],
                    'precioVenta' => $totals['precio_venta'],
                ],
                'detalles' => $details,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function extraerDetalles(Worksheet $sheet, int $priceRow): array
    {
        $startRow = max(1, $priceRow - 58);
        $details = [];
        $seen = [];
        $maxColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 12);

        for ($row = $startRow; $row <= $priceRow; $row++) {
            for ($column = 1; $column <= $maxColumn; $column++) {
                $rawLabel = $this->cellValue($sheet, $column, $row);
                if (! is_string($rawLabel)) {
                    continue;
                }

                $label = trim(preg_replace('/\\s+/', ' ', $rawLabel) ?? '');
                $normalized = $this->normalizar($label);
                if ($normalized === '' || isset($seen[$normalized]) || ! $this->esConceptoCalculo($normalized)) {
                    continue;
                }

                $value = $this->numericValueToRight($sheet, $row, $column, min($column + 7, $maxColumn));
                if ($value === null) {
                    continue;
                }

                $seen[$normalized] = true;
                $details[] = [
                    'tipo' => $this->tipoDetalle($normalized),
                    'concepto' => Str::limit($label, 160, ''),
                    'descripcion' => 'Valor preservado desde '.$sheet->getTitle().'!'.Coordinate::stringFromColumnIndex($column).$row,
                    'valor_base' => 0,
                    'porcentaje' => null,
                    'valor' => round($value, 2),
                    'formula' => ['origen' => $sheet->getTitle().'!'.Coordinate::stringFromColumnIndex($column).$row],
                    'calculos_paso_a_paso' => ['importado_desde_excel' => true],
                    'orden' => count($details) + 1,
                ];
            }
        }

        return $details;
    }

    private function esConceptoCalculo(string $label): bool
    {
        return preg_match('/\\b(SUELDO|BONO|ASIGNACION|HABER|IMPOSIBLE|COTIZ|REFPREV|SIS|MUTUAL|CESANT|VACACION|INDEMNIZ|PROVISION|GASTO|SEGURO|UNIFORME|CASINO|BENEFICIO|COSTO BRUTO|SUBTOTAL|MARGEN|PRECIO VENTA)\\b/', $label) === 1;
    }

    private function tipoDetalle(string $label): string
    {
        if (preg_match('/\\b(REFPREV|SIS|MUTUAL|CESANT|COTIZ)\\b/', $label) === 1) {
            return 'cotizacion';
        }
        if (preg_match('/\\b(VACACION|INDEMNIZ|PROVISION)\\b/', $label) === 1) {
            return 'provision';
        }
        if (str_contains($label, 'MARGEN')) {
            return 'margen';
        }
        if (preg_match('/\\b(GASTO|SEGURO|UNIFORME|CASINO|BENEFICIO)\\b/', $label) === 1) {
            return 'gasto';
        }

        return 'remuneracion';
    }

    /** @param list<array<string, mixed>> $details */
    private function resolverTotales(array $details, float $price): array
    {
        $lookup = function (array $needles) use ($details): float {
            foreach ($details as $detail) {
                $concept = $this->normalizar((string) $detail['concepto']);
                foreach ($needles as $needle) {
                    if (str_contains($concept, $needle)) {
                        return (float) $detail['valor'];
                    }
                }
            }

            return 0.0;
        };

        $subtotal = $lookup(['COSTO BRUTO', 'SUBTOTAL']);
        $margin = $lookup(['MARGEN']);
        if ($margin <= 0 && $subtotal > 0 && $price > $subtotal) {
            $margin = $price - $subtotal;
        }
        if ($subtotal <= 0 && $price > 0) {
            $subtotal = max(0, $price - $margin);
        }

        return [
            'remuneraciones' => $lookup(['TOTAL HABERES', 'TOTAL REMUNERACIONES']),
            'cotizaciones' => $lookup(['TOTAL COTIZACIONES']),
            'provisiones' => $lookup(['TOTAL PROVISIONES']),
            'gastos' => $lookup(['TOTAL GASTOS']),
            'subtotal' => round($subtotal, 2),
            'margen' => round($margin, 2),
            'precio_venta' => round($price, 2),
        ];
    }

    /** @param list<array<string, mixed>> $details */
    private function extraerMargenPorcentaje(array $details): float
    {
        foreach ($details as $detail) {
            if (str_contains($this->normalizar((string) $detail['concepto']), 'MARGEN') && (float) $detail['valor'] > 0 && (float) $detail['valor'] <= 100) {
                return (float) $detail['valor'];
            }
        }

        return 0.0;
    }

    private function numericValueToRight(Worksheet $sheet, int $row, int $column, int $endColumn): ?float
    {
        for ($right = $column + 1; $right <= $endColumn; $right++) {
            $value = $this->cellValue($sheet, $right, $row);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function rowTextValues(Worksheet $sheet, int $row, int $maxColumns): array
    {
        $values = [];
        for ($column = 1; $column <= $maxColumns; $column++) {
            $value = $this->cellValue($sheet, $column, $row);
            if (is_string($value) && trim($value) !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        return array_values(array_filter(explode(' ', $this->normalizar($value)), fn (string $token) => strlen($token) >= 2));
    }

    private function normalizar(string $value): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', Str::upper(Str::ascii($value))) ?? '');
    }

    private function cell(Worksheet $sheet, int $column, int $row)
    {
        return $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);
    }

    private function cellValue(Worksheet $sheet, int $column, int $row): mixed
    {
        return $this->cell($sheet, $column, $row)->getValue();
    }
}
