<?php

namespace App\Console\Commands;

use App\Models\StopObservacion;
use App\Services\GoogleDriveService;
use Google\Service\Sheets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StopSyncSheets extends Command
{
    protected $signature = 'stop:sync-sheets {--force : Forzar re-importación aunque el archivo no haya cambiado}';
    protected $description = 'Sincroniza datos de Google Sheets a la tabla stop_observaciones';

    private const SHEET_NAME = 'Respuestas de formulario 1';

    public function handle(): int
    {
        $drive = new GoogleDriveService();

        if (!$drive->isConfigured()) {
            $this->error('Google Drive no está configurado.');
            return self::FAILURE;
        }

        $fileInfo = $drive->getLatestFile();

        if (!$fileInfo) {
            $this->error('No se encontraron archivos en Google Drive.');
            return self::FAILURE;
        }

        $fileId = $fileInfo['id'];
        $modifiedTime = $fileInfo['modifiedTime'];

        // Verificar si ya está sincronizado (mismo archivo, misma fecha de modificación)
        if (!$this->option('force')) {
            $lastRow = StopObservacion::where('gdrive_file_id', $fileId)->first();
            if ($lastRow) {
                $this->info('Datos ya sincronizados para este archivo. Use --force para re-importar.');
                return self::SUCCESS;
            }
        }

        $this->info("Sincronizando archivo: {$fileInfo['name']}");
        $this->info("ID: {$fileId} | Modificado: {$modifiedTime}");

        try {
            $client = $this->getGoogleClient($drive);
            $sheets = new Sheets($client);

            // Verificar hoja
            $spreadsheet = $sheets->spreadsheets->get($fileId);
            $sheetTitle = self::SHEET_NAME;
            $found = false;
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetTitle) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $sheetTitle = $spreadsheet->getSheets()[0]->getProperties()->getTitle();
                $this->warn("Hoja '" . self::SHEET_NAME . "' no encontrada, usando: {$sheetTitle}");
            }

            // Leer headers
            $headerResp = $sheets->spreadsheets_values->get($fileId, "'{$sheetTitle}'!1:1");
            $allHeaders = $headerResp->getValues()[0] ?? [];

            if (empty($allHeaders)) {
                $this->error('No se encontraron headers en la hoja.');
                return self::FAILURE;
            }

            // Identificar columnas de checklist (índice 19+)
            $checklistPrefixes = [
                'EPP'              => 'EPP [',
                'Reglas de ORO'    => 'Reglas de ORO [',
                'Equipos Móviles'  => 'Observaciones de operación de equipos móviles [',
                'Procedimientos'   => 'Seleccione los siguientes procedimientos [',
                'Operación Segura' => 'Responda las siguientes preguntas',
            ];

            $evalCols = [];
            foreach ($allHeaders as $idx => $header) {
                if ($idx < 19) continue;
                $h = trim($header);
                foreach ($checklistPrefixes as $catName => $prefix) {
                    if (stripos($h, $prefix) !== false) {
                        $question = $h;
                        if (preg_match('/\[(.+)\]/', $h, $m)) {
                            $question = trim($m[1]);
                        }
                        $evalCols[$idx] = ['category' => $catName, 'question' => $question];
                        break;
                    }
                }
            }

            $maxCol = !empty($evalCols) ? max(array_keys($evalCols)) : 18;
            $maxColLetter = $this->colIndexToLetter($maxCol);

            // Truncar tabla antes de importar
            $this->info('Limpiando datos anteriores...');
            StopObservacion::truncate();

            // Leer e importar en batches de 3000 filas
            $batchSize = 3000;
            $startRow = 2;
            $totalImported = 0;
            $skipped = 0;

            $bar = $this->output->createProgressBar();
            $bar->start();

            while (true) {
                $endRow = $startRow + $batchSize - 1;
                $range = "'{$sheetTitle}'!A{$startRow}:{$maxColLetter}{$endRow}";

                $response = $sheets->spreadsheets_values->get($fileId, $range);
                $values = $response->getValues();

                if (empty($values)) {
                    break;
                }

                $inserts = [];

                foreach ($values as $idx => $row) {
                    $rowNum = $startRow + $idx;
                    $centro = trim($row[4] ?? '');
                    $empresaObservado = trim($row[14] ?? '');

                    // Saltar fila de prueba
                    if (strtolower($centro) === 'prueba') {
                        $skipped++;
                        continue;
                    }

                    // Solo importar registros de la empresa configurada (SAEP por defecto)
                    $empresaFiltro = \App\Models\Configuracion::get('stop_report_empresa', 'SAEP');
                    if ($empresaFiltro && mb_strtoupper(trim($empresaObservado)) !== mb_strtoupper(trim($empresaFiltro))) {
                        $skipped++;
                        continue;
                    }

                    $marcaTemporal = $this->parseTimestamp(trim($row[0] ?? ''));
                    $fechaTarjeta = $this->parseDate(trim($row[2] ?? ''));

                    // Recopilar datos de checklist como JSON
                    $checklistData = null;
                    if (!empty($evalCols)) {
                        $checkItems = [];
                        foreach ($evalCols as $colIdx => $info) {
                            $val = strtoupper(trim($row[$colIdx] ?? ''));
                            if ($val === '' || $val === '-' || $val === 'PRUEBA') continue;
                            $checkItems[] = [
                                'cat' => $info['category'],
                                'q'   => $info['question'],
                                'val' => $val,
                            ];
                        }
                        if (!empty($checkItems)) {
                            $checklistData = json_encode($checkItems, JSON_UNESCAPED_UNICODE);
                        }
                    }

                    $inserts[] = [
                        'gdrive_file_id'     => $fileId,
                        'row_number'         => $rowNum,
                        'marca_temporal'     => $marcaTemporal,
                        'correo'             => mb_substr(trim($row[1] ?? ''), 0, 200),
                        'fecha_tarjeta'      => $fechaTarjeta,
                        'hora_observacion'   => mb_substr(trim($row[3] ?? ''), 0, 20),
                        'centro'             => mb_substr($centro, 0, 150),
                        'empresa_observador' => mb_substr(trim($row[5] ?? ''), 0, 200),
                        'nombre_observador'  => mb_substr(trim($row[6] ?? ''), 0, 200),
                        'rut_observador'     => mb_substr(trim($row[7] ?? ''), 0, 20),
                        'clasificacion'      => mb_substr(trim($row[8] ?? ''), 0, 30),
                        'turno'              => mb_substr(trim($row[9] ?? ''), 0, 50),
                        'nombre_observado'   => mb_substr(trim($row[10] ?? ''), 0, 200),
                        'interno_externo'    => mb_substr(trim($row[11] ?? ''), 0, 20),
                        'antiguedad'         => mb_substr(trim($row[12] ?? ''), 0, 80),
                        'area_proceso'       => mb_substr(trim($row[13] ?? ''), 0, 150),
                        'empresa_observado'  => mb_substr(trim($row[14] ?? ''), 0, 200),
                        'cargo_observado'    => mb_substr(trim($row[15] ?? ''), 0, 150),
                        'tipo_observacion'   => mb_substr(trim($row[16] ?? ''), 0, 200),
                        'empresa_ruta'       => mb_substr(trim($row[17] ?? ''), 0, 200),
                        'tipo_observacion_b' => mb_substr(trim($row[18] ?? ''), 0, 200),
                        'checklist_data'     => $checklistData,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }

                // Bulk insert
                if (!empty($inserts)) {
                    foreach (array_chunk($inserts, 500) as $chunk) {
                        DB::table('stop_observaciones')->insert($chunk);
                    }
                    $totalImported += count($inserts);
                }

                $bar->advance(count($values));

                // Si el batch devolvió menos filas que el tamaño, terminamos
                if (count($values) < $batchSize) {
                    break;
                }

                $startRow = $endRow + 1;
                unset($values, $response, $inserts);
            }

            $bar->finish();
            $this->newLine();

            // Limpiar caché de Google Drive
            $drive->clearCache();

            $this->info("Sincronización completada: {$totalImported} registros importados, {$skipped} omitidos.");
            Log::info('stop:sync-sheets completado', [
                'file'     => $fileInfo['name'],
                'imported' => $totalImported,
                'skipped'  => $skipped,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error durante sincronización: {$e->getMessage()}");
            Log::error('stop:sync-sheets error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    private function getGoogleClient(GoogleDriveService $drive): \Google\Client
    {
        // Usar reflexión para acceder al client ya configurado del service
        $ref = new \ReflectionMethod($drive, 'getClient');
        $ref->setAccessible(true);
        return $ref->invoke($drive);
    }

    private function parseTimestamp(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) return null;

        // d/m/Y H:i:s
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?#', $value, $m)) {
            return sprintf('%s-%02d-%02d %02d:%02d:%02d', $m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5], (int)($m[6] ?? 0));
        }
        // Y-m-d H:i:s
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?#', $value, $m)) {
            return sprintf('%s-%02d-%02d %02d:%02d:%02d', $m[1], (int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5], (int)($m[6] ?? 0));
        }
        // Fallback to date only
        return $this->parseDate($value) ? $this->parseDate($value) . ' 00:00:00' : null;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) return null;

        // d/m/Y
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], (int)$m[2], (int)$m[1]);
        }
        // Y-m-d
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $value, $m)) {
            return sprintf('%s-%02d-%02d', $m[1], (int)$m[2], (int)$m[3]);
        }
        return null;
    }

    private function colIndexToLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }
}
