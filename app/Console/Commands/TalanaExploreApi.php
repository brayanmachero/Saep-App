<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TalanaExploreApi extends Command
{
    protected $signature = 'talana:explorar-api
        {--base-url= : URL base de Talana}
        {--token= : Token de Talana (si no se envía, usa configuración)}
        {--auth-scheme= : Esquema de Authorization: auto|token|bearer}
        {--company= : ID de empresa (opcional para algunos endpoints)}
        {--rut= : RUT para consultas puntuales (opcional)}
        {--page-size=5 : Tamaño de muestra por endpoint}
        {--timeout=25 : Timeout en segundos por request}
        {--output= : Nombre de archivo JSON de salida (opcional)}';

    protected $description = 'Explora endpoints de Talana en modo lectura y genera un reporte de disponibilidad de datos';

    public function handle(): int
    {
        $token = $this->resolveToken();
        if (!$token) {
            $this->error('No se encontró token. Usa --token, TALANA_API_TOKEN o integracion_talana_api_key en configuraciones.');
            return self::FAILURE;
        }

        $baseUrl = $this->resolveBaseUrl();
        $pageSize = max(1, (int) $this->option('page-size'));
        $timeout = max(5, (int) $this->option('timeout'));
        $company = $this->option('company');
        $rut = $this->option('rut');
        $authScheme = $this->resolveRequestedAuthScheme();

        if (!in_array($authScheme, ['auto', 'token', 'bearer'], true)) {
            $this->error('Valor inválido para --auth-scheme. Usa: auto, token o bearer.');
            return self::FAILURE;
        }

        $resolvedAuthScheme = $this->resolveAuthScheme($baseUrl, $token, $timeout, $authScheme);

        $this->line('Explorando Talana (solo lectura) ...');
        $this->line('Base URL: ' . $baseUrl);
        $this->line('Auth scheme: ' . strtoupper($resolvedAuthScheme));

        $endpoints = $this->buildEndpoints($pageSize, $company, $rut);
        $results = [];

        foreach ($endpoints as $endpoint) {
            $result = $this->probeEndpoint($baseUrl, $token, $timeout, $endpoint, $resolvedAuthScheme);
            $results[] = $result;

            $statusText = (string) $result['status'];
            $messageText = (string) $result['message'];
            $this->line(sprintf('[%s] %s - %s', $statusText, $endpoint['name'], $messageText));
        }

        $summary = [
            'ok' => collect($results)->whereIn('status', [200, 201])->count(),
            'auth_issues' => collect($results)->whereIn('status', [401, 403])->count(),
            'not_found' => collect($results)->where('status', 404)->count(),
            'bad_request' => collect($results)->where('status', 400)->count(),
            'other' => collect($results)->whereNotIn('status', [200, 201, 400, 401, 403, 404])->count(),
        ];

        $this->newLine();
        $this->info('Resumen de exploración:');
        $this->line('- Endpoints OK: ' . $summary['ok']);
        $this->line('- Problemas de autorización (401/403): ' . $summary['auth_issues']);
        $this->line('- Endpoints no encontrados (404): ' . $summary['not_found']);
        $this->line('- Endpoints con parámetros faltantes (400): ' . $summary['bad_request']);
        $this->line('- Otros estados: ' . $summary['other']);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'base_url' => $baseUrl,
            'auth_scheme' => $resolvedAuthScheme,
            'page_size' => $pageSize,
            'company' => $company,
            'rut' => $rut,
            'summary' => $summary,
            'results' => $results,
        ];

        $defaultFile = 'talana/exploracion_' . now()->format('Ymd_His') . '.json';
        $outputFile = $this->option('output') ? (string) $this->option('output') : $defaultFile;

        Storage::disk('local')->put($outputFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Reporte guardado en storage/app/private/' . $outputFile);

        return self::SUCCESS;
    }

    private function resolveBaseUrl(): string
    {
        $optionValue = trim((string) $this->option('base-url'));
        if ($optionValue !== '') {
            return rtrim($optionValue, '/');
        }

        $configValue = trim((string) config('services.talana.base_url', 'https://sandbox.talana.dev/es/api'));
        return rtrim($configValue, '/');
    }

    private function resolveRequestedAuthScheme(): string
    {
        $optionValue = strtolower(trim((string) $this->option('auth-scheme')));
        if ($optionValue !== '') {
            return $optionValue;
        }

        $configValue = strtolower(trim((string) config('services.talana.auth_scheme', 'token')));
        return $configValue !== '' ? $configValue : 'token';
    }

    private function resolveToken(): ?string
    {
        $tokenOption = trim((string) $this->option('token'));
        if ($tokenOption !== '') {
            return $tokenOption;
        }

        $tokenFromConfigServices = trim((string) config('services.talana.token', ''));
        if ($tokenFromConfigServices !== '') {
            return $tokenFromConfigServices;
        }

        $tokenFromConfig = null;
        try {
            $tokenFromConfig = Configuracion::get('integracion_talana_api_key');
        } catch (\Throwable) {
            $tokenFromConfig = null;
        }

        $tokenFromConfig = trim((string) $tokenFromConfig);
        if ($tokenFromConfig !== '') {
            return $tokenFromConfig;
        }

        return null;
    }

    private function buildEndpoints(int $pageSize, $company, $rut): array
    {
        return [
            [
                'name' => 'Fecha servidor',
                'path' => '/fechaserver/',
                'query' => [],
                'focus' => 'Conectividad base',
            ],
            [
                'name' => 'Personas (paginado)',
                'path' => '/personas-paginadas/',
                'query' => [
                    'page' => 1,
                    'page_size' => $pageSize,
                ],
                'focus' => 'Gestión de Personas',
            ],
            [
                'name' => 'Contratos (paginado)',
                'path' => '/contrato-paginado/',
                'query' => [
                    'page' => 1,
                    'page_size' => $pageSize,
                ],
                'focus' => 'Renovaciones y vigencias',
            ],
            [
                'name' => 'Horas extras',
                'path' => '/horas-extras/',
                'query' => [
                    'page' => 1,
                    'page_size' => $pageSize,
                ],
                'focus' => 'Asistencia y Turnos',
            ],
            [
                'name' => 'Marcas de asistencia',
                'path' => '/mark/',
                'query' => [
                    'page' => 1,
                    'page_size' => $pageSize,
                ],
                'focus' => 'Asistencia y Turnos',
            ],
            [
                'name' => 'Días trabajados',
                'path' => '/workedDays',
                'query' => [],
                'focus' => 'Asistencia y Turnos',
            ],
            [
                'name' => 'Periodos de trabajo',
                'path' => '/periodos/',
                'query' => [],
                'focus' => 'Contexto de periodos',
            ],
        ];
    }

    private function resolveAuthScheme(string $baseUrl, string $token, int $timeout, string $requested): string
    {
        if ($requested !== 'auto') {
            return $requested;
        }

        // La documentación oficial de Talana indica Authorization: Token <token>.
        // Mantener auto como alias de token para evitar falsos negativos por probar Bearer.
        return 'token';
    }

    private function pingStatus(string $url, string $token, int $timeout, string $scheme): int
    {
        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => $this->buildAuthorizationHeader($scheme, $token),
                ])
                ->get($url);

            return $response->status();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function probeEndpoint(string $baseUrl, string $token, int $timeout, array $endpoint, string $authScheme): array
    {
        $url = $baseUrl . $endpoint['path'];
        $query = $endpoint['query'] ?? [];

        if ($this->option('company') !== null && !array_key_exists('company', $query)) {
            $query['company'] = $this->option('company');
        }
        if ($this->option('rut') !== null && !array_key_exists('rut', $query)) {
            $query['rut'] = $this->option('rut');
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => $this->buildAuthorizationHeader($authScheme, $token),
                ])
                ->get($url, $query);

            $json = $response->json();
            $sample = is_array($json) ? $this->extractSample($json) : null;

            return [
                'name' => $endpoint['name'],
                'focus' => $endpoint['focus'],
                'url' => $url,
                'query' => $query,
                'status' => $response->status(),
                'message' => $this->resolveMessage($response->status(), $json),
                'sample' => $sample,
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $endpoint['name'],
                'focus' => $endpoint['focus'],
                'url' => $url,
                'query' => $query,
                'status' => 0,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'sample' => null,
            ];
        }
    }

    private function resolveMessage(int $status, $json): string
    {
        if (is_array($json)) {
            foreach (['detail', 'message', 'error'] as $key) {
                if (!empty($json[$key]) && is_string($json[$key])) {
                    return $json[$key];
                }
            }
        }

        return match (true) {
            $status >= 200 && $status < 300 => 'OK',
            $status === 400 => 'Solicitud inválida (posibles parámetros requeridos)',
            $status === 401 => 'Token inválido o no autorizado',
            $status === 403 => 'Sin permisos para este recurso',
            $status === 404 => 'Endpoint no encontrado o no habilitado',
            $status === 429 => 'Rate limit excedido',
            $status >= 500 => 'Error del servidor Talana',
            default => 'Respuesta no controlada',
        };
    }

    private function buildAuthorizationHeader(string $scheme, string $token): string
    {
        return match ($scheme) {
            'token' => 'Token ' . $token,
            default => 'Bearer ' . $token,
        };
    }

    private function extractSample(array $json): ?array
    {
        if (array_key_exists('results', $json) && is_array($json['results'])) {
            return [
                'count' => $json['count'] ?? null,
                'results_count' => count($json['results']),
                'first_result_keys' => isset($json['results'][0]) && is_array($json['results'][0])
                    ? array_slice(array_keys($json['results'][0]), 0, 15)
                    : [],
            ];
        }

        return [
            'top_level_keys' => array_slice(array_keys($json), 0, 20),
        ];
    }
}
