<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    private ?Client $client = null;
    private string $credentialsPath;
    private string $folderId;

    private const SHEET_NAME = 'Respuestas de formulario 1';

    /**
     * Columnas de metadata que necesitamos para el dashboard (A-S = índices 0-18).
     * Definimos los nombres esperados para mapear por posición.
     */
    private const META_COLUMNS = [
        0  => 'marca_temporal',
        1  => 'correo',
        2  => 'fecha_tarjeta',
        3  => 'hora_observacion',
        4  => 'centro',
        5  => 'empresa_observador',
        6  => 'nombre_observador',
        7  => 'rut_observador',
        8  => 'clasificacion',       // Positiva / Negativa
        9  => 'turno',
        10 => 'nombre_observado',
        11 => 'interno_externo',     // Interno / Externo
        12 => 'antiguedad',
        13 => 'area_proceso',
        14 => 'empresa_observado',
        15 => 'cargo_observado_b',
        16 => 'tipo_observacion_a',  // EPP, Reglas de ORO, Operación Segura, etc.
        17 => 'empresa_ruta',
        18 => 'tipo_observacion_b',
    ];

    public function __construct()
    {
        $config = config('services.google_drive');
        $this->credentialsPath = base_path($config['credentials_path'] ?? 'google-credentials.json');
        $this->folderId = $config['folder_id'] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->folderId && file_exists($this->credentialsPath);
    }

    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = new Client();
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setScopes([
            'https://www.googleapis.com/auth/drive.readonly',
            'https://www.googleapis.com/auth/spreadsheets.readonly',
        ]);

        return $this->client;
    }

    /**
     * Obtener el archivo más reciente de la carpeta configurada.
     */
    public function getLatestFile(): ?array
    {
        return Cache::remember('gdrive_latest_file_info', 300, function () {
            try {
                $drive = new Drive($this->getClient());

                $results = $drive->files->listFiles([
                    'q'        => "'{$this->folderId}' in parents and trashed = false",
                    'orderBy'  => 'modifiedTime desc',
                    'pageSize' => 5,
                    'fields'   => 'files(id, name, mimeType, modifiedTime, size)',
                ]);

                $files = $results->getFiles();
                if (empty($files)) {
                    Log::warning('GoogleDrive: No se encontraron archivos en la carpeta');
                    return null;
                }

                $latest = $files[0];
                return [
                    'id'           => $latest->getId(),
                    'name'         => $latest->getName(),
                    'mimeType'     => $latest->getMimeType(),
                    'modifiedTime' => $latest->getModifiedTime(),
                    'size'         => $latest->getSize(),
                ];
            } catch (\Exception $e) {
                Log::error('GoogleDrive: Error listando archivos', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Obtener analíticas pre-calculadas del dashboard STOP.
     * Solo lee las columnas de metadata (A-S) para evitar problemas de memoria con 26K+ filas.
     */
    public function getStopAnalytics(): ?array
    {
        $fileInfo = $this->getLatestFile();
        if (!$fileInfo || $fileInfo['mimeType'] !== 'application/vnd.google-apps.spreadsheet') {
            return null;
        }

        $cacheKey = 'gdrive_stop_analytics_' . md5($fileInfo['id'] . $fileInfo['modifiedTime']);

        return Cache::remember($cacheKey, 3600, function () use ($fileInfo) {
            try {
                return $this->computeAnalytics($fileInfo['id']);
            } catch (\Exception $e) {
                Log::error('GoogleDrive: Error calculando analíticas STOP', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return null;
            }
        });
    }

    /**
     * Leer columnas de metadata (A-S) y calcular analíticas.
     */
    private function computeAnalytics(string $spreadsheetId): array
    {
        $sheets = new Sheets($this->getClient());

        // Verificar que la hoja existe
        $spreadsheet = $sheets->spreadsheets->get($spreadsheetId);
        $sheetTitle = null;
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === self::SHEET_NAME) {
                $sheetTitle = self::SHEET_NAME;
                break;
            }
        }
        if (!$sheetTitle) {
            $sheetTitle = $spreadsheet->getSheets()[0]->getProperties()->getTitle();
            Log::warning('GoogleDrive: Hoja "' . self::SHEET_NAME . '" no encontrada, usando: ' . $sheetTitle);
        }

        // Leer solo columnas A-S (metadata) — mucho más eficiente en memoria
        $range = "'{$sheetTitle}'!A:S";
        $response = $sheets->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values) || count($values) < 2) {
            return ['totalRows' => 0];
        }

        $realHeaders = $values[0];
        $totalRows = count($values) - 1; // Excluir header

        // Inicializar contadores
        $clasificacion = [];      // Positiva/Negativa
        $centros = [];
        $areas = [];
        $tiposObservacion = [];
        $internoExterno = [];
        $empresas = [];
        $turnos = [];
        $observadores = [];       // top observadores
        $byMonth = [];            // timeline mensual
        $byYear = [];
        $antiguedades = [];

        // Fila de prueba (primera fila con "Prueba") — omitir
        $skipTest = true;

        for ($i = 1; $i <= $totalRows; $i++) {
            $row = $values[$i] ?? [];

            // Acceder por posición (más eficiente que arrays asociativos)
            $marcaTemporal  = trim($row[0] ?? '');
            $fechaTarjeta   = trim($row[2] ?? '');
            $centro         = trim($row[4] ?? '');
            $empresaObs     = trim($row[5] ?? '');
            $nombreObs      = trim($row[6] ?? '');
            $clasif         = trim($row[8] ?? '');
            $turno          = trim($row[9] ?? '');
            $internoExt     = trim($row[11] ?? '');
            $antiguedad     = trim($row[12] ?? '');
            $area           = trim($row[13] ?? '');
            $empresaObsdo   = trim($row[14] ?? '');
            $tipoObs        = trim($row[16] ?? '');

            // Omitir fila de prueba
            if ($skipTest && strtolower($centro) === 'prueba') {
                $totalRows--;
                continue;
            }
            $skipTest = false;

            // Clasificación (Positiva/Negativa)
            if ($clasif !== '') {
                $clasificacion[$clasif] = ($clasificacion[$clasif] ?? 0) + 1;
            }

            // Centro
            if ($centro !== '') {
                $centros[$centro] = ($centros[$centro] ?? 0) + 1;
            }

            // Área o proceso
            if ($area !== '') {
                $areas[$area] = ($areas[$area] ?? 0) + 1;
            }

            // Tipo de observación
            if ($tipoObs !== '') {
                $tiposObservacion[$tipoObs] = ($tiposObservacion[$tipoObs] ?? 0) + 1;
            }

            // Interno / Externo
            if ($internoExt !== '') {
                $internoExterno[$internoExt] = ($internoExterno[$internoExt] ?? 0) + 1;
            }

            // Empresa observado
            if ($empresaObsdo !== '') {
                $empresas[$empresaObsdo] = ($empresas[$empresaObsdo] ?? 0) + 1;
            }

            // Turno
            if ($turno !== '') {
                $turnos[$turno] = ($turnos[$turno] ?? 0) + 1;
            }

            // Antigüedad
            if ($antiguedad !== '') {
                $antiguedades[$antiguedad] = ($antiguedades[$antiguedad] ?? 0) + 1;
            }

            // Observador (para top)
            if ($nombreObs !== '') {
                $key = mb_strtoupper($nombreObs);
                $observadores[$key] = ($observadores[$key] ?? 0) + 1;
            }

            // Timeline mensual (parsear fecha)
            $fecha = $fechaTarjeta ?: $marcaTemporal;
            if ($fecha !== '') {
                $monthKey = $this->extractMonthKey($fecha);
                if ($monthKey) {
                    $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
                }
                $yearKey = substr($monthKey ?? '', 0, 4);
                if ($yearKey && $yearKey !== '') {
                    $byYear[$yearKey] = ($byYear[$yearKey] ?? 0) + 1;
                }
            }
        }

        // Ordenar distribuciones
        arsort($clasificacion);
        arsort($centros);
        arsort($areas);
        arsort($tiposObservacion);
        arsort($empresas);
        arsort($observadores);
        arsort($turnos);
        arsort($antiguedades);
        ksort($byMonth);
        ksort($byYear);

        // Limitar top observadores
        $topObservadores = array_slice($observadores, 0, 20, true);
        $topEmpresas = array_slice($empresas, 0, 15, true);

        return [
            'totalRows'        => $totalRows,
            'clasificacion'    => $clasificacion,
            'centros'          => $centros,
            'areas'            => $areas,
            'tiposObservacion' => $tiposObservacion,
            'internoExterno'   => $internoExterno,
            'empresas'         => $topEmpresas,
            'turnos'           => $turnos,
            'antiguedades'     => $antiguedades,
            'topObservadores'  => $topObservadores,
            'byMonth'          => $byMonth,
            'byYear'           => $byYear,
            'headers'          => $realHeaders,
        ];
    }

    /**
     * Obtener análisis de cumplimiento de checklist (EPP, Reglas, etc.)
     * Lee solo las columnas de checklist en un rango específico.
     */
    public function getChecklistAnalytics(): ?array
    {
        $fileInfo = $this->getLatestFile();
        if (!$fileInfo || $fileInfo['mimeType'] !== 'application/vnd.google-apps.spreadsheet') {
            return null;
        }

        $cacheKey = 'gdrive_stop_checklist_' . md5($fileInfo['id'] . $fileInfo['modifiedTime']);

        return Cache::remember($cacheKey, 3600, function () use ($fileInfo) {
            try {
                return $this->computeChecklistAnalytics($fileInfo['id']);
            } catch (\Exception $e) {
                Log::error('GoogleDrive: Error calculando checklist', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Lee columnas de checklist (T en adelante) y calcula tasas de cumplimiento.
     */
    private function computeChecklistAnalytics(string $spreadsheetId): array
    {
        $sheets = new Sheets($this->getClient());

        $spreadsheet = $sheets->spreadsheets->get($spreadsheetId);
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
        }

        // Leer headers completos primero
        $headerResp = $sheets->spreadsheets_values->get($spreadsheetId, "'{$sheetTitle}'!1:1");
        $allHeaders = $headerResp->getValues()[0] ?? [];

        // Identificar columnas de checklist (tienen prefijos conocidos)
        $checklistPrefixes = [
            'EPP'                          => 'EPP [',
            'Reglas de ORO'                => 'Reglas de ORO [',
            'Equipos Móviles'              => 'Observaciones de operación de equipos móviles [',
            'Buenas Prácticas'             => 'Cumplimiento de buenas prácticas [',
            'Procedimientos'               => 'Seleccione los siguientes procedimientos [',
            'Preguntas Generales'          => 'Responda las siguientes preguntas',
        ];

        // Mapear headers a categorías
        $checklistCols = []; // index => [category, question]
        foreach ($allHeaders as $idx => $header) {
            $h = trim($header);
            foreach ($checklistPrefixes as $catName => $prefix) {
                if (stripos($h, $prefix) !== false) {
                    $question = $h;
                    if (preg_match('/\[(.+)\]/', $h, $m)) {
                        $question = trim($m[1]);
                    }
                    $checklistCols[$idx] = ['category' => $catName, 'question' => $question];
                    break;
                }
            }
        }

        if (empty($checklistCols)) {
            return ['categories' => []];
        }

        // Encontrar rango de columnas de checklist (mín-máx)
        $colIndices = array_keys($checklistCols);
        $minCol = min($colIndices);
        $maxCol = max($colIndices);

        // Convertir a letra de columna de Excel
        $startCol = $this->colIndexToLetter($minCol);
        $endCol = $this->colIndexToLetter($maxCol);

        // Leer todas las filas pero solo las columnas de checklist
        $range = "'{$sheetTitle}'!{$startCol}:{$endCol}";
        $response = $sheets->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values) || count($values) < 2) {
            return ['categories' => []];
        }

        // Inicializar contadores por categoría y por pregunta
        $catStats = [];     // category => [cumple, no_cumple, total]
        $questionStats = []; // category => [question => [cumple, no_cumple]]

        foreach ($checklistPrefixes as $catName => $_) {
            $catStats[$catName] = ['cumple' => 0, 'no_cumple' => 0, 'total' => 0];
            $questionStats[$catName] = [];
        }

        // Procesar fila por fila
        for ($i = 1; $i < count($values); $i++) {
            $row = $values[$i] ?? [];

            foreach ($checklistCols as $origIdx => $info) {
                // Ajustar índice relativo al rango leído
                $relIdx = $origIdx - $minCol;
                $val = strtoupper(trim($row[$relIdx] ?? ''));

                if ($val === '' || $val === '-' || $val === 'PRUEBA') continue;

                $cat = $info['category'];
                $q = $info['question'];

                if (!isset($questionStats[$cat][$q])) {
                    $questionStats[$cat][$q] = ['cumple' => 0, 'no_cumple' => 0];
                }

                if (str_contains($val, 'NO CUMPLE')) {
                    $catStats[$cat]['no_cumple']++;
                    $catStats[$cat]['total']++;
                    $questionStats[$cat][$q]['no_cumple']++;
                } elseif (str_contains($val, 'CUMPLE')) {
                    $catStats[$cat]['cumple']++;
                    $catStats[$cat]['total']++;
                    $questionStats[$cat][$q]['cumple']++;
                }
                // Si es N/A o vacío, no contar
            }
        }

        // Calcular porcentajes de cumplimiento
        $categories = [];
        foreach ($catStats as $catName => $stats) {
            if ($stats['total'] === 0) continue;

            $pct = round(($stats['cumple'] / $stats['total']) * 100, 1);

            // Ordenar preguntas por no-cumplimiento (más problemáticas primero)
            $questions = $questionStats[$catName];
            uasort($questions, fn($a, $b) => $b['no_cumple'] - $a['no_cumple']);

            $categories[$catName] = [
                'cumple'     => $stats['cumple'],
                'no_cumple'  => $stats['no_cumple'],
                'total'      => $stats['total'],
                'pct_cumple' => $pct,
                'questions'  => $questions,
            ];
        }

        // Ordenar por % cumplimiento ascendente (peores primero)
        uasort($categories, fn($a, $b) => $a['pct_cumple'] <=> $b['pct_cumple']);

        return ['categories' => $categories];
    }

    /**
     * Convertir índice de columna (0-based) a letra Excel.
     */
    private function colIndexToLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }

    /**
     * Extraer clave de mes (YYYY-MM) de una fecha en varios formatos.
     */
    private function extractMonthKey(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) return null;

        // d/m/Y o d/m/Y H:i:s
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
            return sprintf('%s-%02d', $m[3], (int)$m[2]);
        }
        // Y-m-d
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $value, $m)) {
            return sprintf('%s-%02d', $m[1], (int)$m[2]);
        }
        return null;
    }

    /**
     * Forzar recarga de datos (limpiar cache).
     */
    public function clearCache(): void
    {
        Cache::forget('gdrive_latest_file_info');

        $fileInfo = $this->getLatestFile();
        if ($fileInfo) {
            $key = md5($fileInfo['id'] . $fileInfo['modifiedTime']);
            Cache::forget('gdrive_stop_analytics_' . $key);
            Cache::forget('gdrive_stop_checklist_' . $key);
        }
    }
}
