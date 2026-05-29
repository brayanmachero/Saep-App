<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Comando de AUDITORÍA: sondea todos los endpoints conocidos de la API de Talana
 * para determinar cuáles existen pero están bloqueados (403) vs cuáles no existen (404).
 * Genera un reporte de evidencia para solicitar habilitación de permisos.
 *
 * Uso:
 *   php artisan talana:auditar-endpoints
 *   php artisan talana:auditar-endpoints --fecha=2026-05-28 --timeout=15
 */
class TalanaAuditarEndpoints extends Command
{
    protected $signature = 'talana:auditar-endpoints
                            {--fecha=    : Fecha de referencia YYYY-MM-DD (default: ayer)}
                            {--timeout=10 : Segundos por request (default: 10)}';

    protected $description = '[AUDITORÍA] Sondea todos los endpoints Talana y reporta accesos 200/403/404/timeout para solicitar permisos';

    private string $baseUrl;
    private string $token;
    private array  $headers;
    private int    $timeout;

    // ─── Catálogo completo de endpoints Talana conocidos / inferidos ────────
    // Basado en convención de nombres de la API REST de Talana (Django REST Framework).
    // Formato: 'path/' => [params de prueba]
    private array $endpoints = [
        // ── Asistencia / Marcas ──────────────────────────────────────────────
        'mark/'                              => ['page' => 1, 'page_size' => 1],
        'mark-paginado/'                     => ['page' => 1, 'page_size' => 1],
        'markReport/'                        => ['page' => 1, 'page_size' => 1],
        'marcas/'                            => ['page' => 1, 'page_size' => 1],
        'marcas-paginado/'                   => ['page' => 1, 'page_size' => 1],
        'workedDays'                         => ['page' => 1, 'page_size' => 1],
        'workedDays-paginado/'               => ['page' => 1, 'page_size' => 1],
        'workedTime/'                        => ['page' => 1, 'page_size' => 1],
        'attendanceSummary/'                 => ['page' => 1, 'page_size' => 1],
        'resumen-asistencia/'                => ['page' => 1, 'page_size' => 1],
        'estadoAsistencia/'                  => ['page' => 1, 'page_size' => 1],
        'estadoAsistencia-paginado/'         => ['page' => 1, 'page_size' => 1],
        'asistenciaPersona/'                 => ['page' => 1, 'page_size' => 1],
        'asistenciaPersona-paginado/'        => ['page' => 1, 'page_size' => 1],

        // ── Turnos / Horarios ─────────────────────────────────────────────────
        'rotativeDay/'                       => ['page' => 1, 'page_size' => 1],
        'rotativeDay-paginado/'              => ['page' => 1, 'page_size' => 1],
        'workShift/'                         => ['page' => 1, 'page_size' => 1],
        'workShift-paginado/'                => ['page' => 1, 'page_size' => 1],
        'turno/'                             => ['page' => 1, 'page_size' => 1],
        'turno-paginado/'                    => ['page' => 1, 'page_size' => 1],
        'turnos/'                            => ['page' => 1, 'page_size' => 1],
        'horario/'                           => ['page' => 1, 'page_size' => 1],
        'horario-paginado/'                  => ['page' => 1, 'page_size' => 1],
        'jornada/'                           => ['page' => 1, 'page_size' => 1],
        'jornada-paginado/'                  => ['page' => 1, 'page_size' => 1],
        'jornadaTrabajo/'                    => ['page' => 1, 'page_size' => 1],
        'jornadaTrabajo-paginado/'           => ['page' => 1, 'page_size' => 1],
        'scheduleDay/'                       => ['page' => 1, 'page_size' => 1],
        'scheduleDay-paginado/'              => ['page' => 1, 'page_size' => 1],
        'personaTurno/'                      => ['page' => 1, 'page_size' => 1],
        'personaTurno-paginado/'             => ['page' => 1, 'page_size' => 1],
        'asignacionTurno/'                   => ['page' => 1, 'page_size' => 1],
        'asignacion-turno/'                  => ['page' => 1, 'page_size' => 1],
        'asignacion-turno-paginado/'         => ['page' => 1, 'page_size' => 1],
        'turnoPersona/'                      => ['page' => 1, 'page_size' => 1],
        'turnoPersona-paginado/'             => ['page' => 1, 'page_size' => 1],
        'turnoAsignado/'                     => ['page' => 1, 'page_size' => 1],
        'turnoAsignado-paginado/'            => ['page' => 1, 'page_size' => 1],
        'assignedShift/'                     => ['page' => 1, 'page_size' => 1],
        'assignedShift-paginado/'            => ['page' => 1, 'page_size' => 1],
        'personSchedule/'                    => ['page' => 1, 'page_size' => 1],
        'personSchedule-paginado/'           => ['page' => 1, 'page_size' => 1],
        'programacionTurno/'                 => ['page' => 1, 'page_size' => 1],
        'programacionTurno-paginado/'        => ['page' => 1, 'page_size' => 1],
        'programacion-turno-paginado/'       => ['page' => 1, 'page_size' => 1],

        // ── Personas / Empleados ──────────────────────────────────────────────
        'persona/'                           => ['page' => 1, 'page_size' => 1],
        'persona-paginado/'                  => ['page' => 1, 'page_size' => 1],
        'empleado/'                          => ['page' => 1, 'page_size' => 1],
        'empleado-paginado/'                 => ['page' => 1, 'page_size' => 1],
        'trabajador/'                        => ['page' => 1, 'page_size' => 1],
        'trabajador-paginado/'               => ['page' => 1, 'page_size' => 1],

        // ── Contratos / Nómina ─────────────────────────────────────────────────
        'contrato/'                          => ['page' => 1, 'page_size' => 1],
        'contrato-paginado/'                 => ['page' => 1, 'page_size' => 1],
        'contratoActivo/'                    => ['page' => 1, 'page_size' => 1],
        'contratoActivo-paginado/'           => ['page' => 1, 'page_size' => 1],

        // ── Ausencias / Licencias / Vacaciones ────────────────────────────────
        'personaAusencia/'                   => ['page' => 1, 'page_size' => 1],
        'personaAusencia-paginado/'          => ['page' => 1, 'page_size' => 1],
        'ausencia/'                          => ['page' => 1, 'page_size' => 1],
        'ausencia-paginado/'                 => ['page' => 1, 'page_size' => 1],
        'licencia/'                          => ['page' => 1, 'page_size' => 1],
        'licencia-paginado/'                 => ['page' => 1, 'page_size' => 1],
        'vacacion/'                          => ['page' => 1, 'page_size' => 1],
        'vacacion-paginado/'                 => ['page' => 1, 'page_size' => 1],
        'permiso/'                           => ['page' => 1, 'page_size' => 1],
        'permiso-paginado/'                  => ['page' => 1, 'page_size' => 1],
        'dayOff/'                            => ['page' => 1, 'page_size' => 1],
        'dayOff-paginado/'                   => ['page' => 1, 'page_size' => 1],
        'tipoAusencia/'                      => ['page' => 1, 'page_size' => 1],
        'tipoAusencia-paginado/'             => ['page' => 1, 'page_size' => 1],
        'absence/'                           => ['page' => 1, 'page_size' => 1],
        'absence-paginado/'                  => ['page' => 1, 'page_size' => 1],

        // ── Otros datos RR.HH ─────────────────────────────────────────────────
        'centroCosto/'                       => ['page' => 1, 'page_size' => 1],
        'centroCosto-paginado/'              => ['page' => 1, 'page_size' => 1],
        'sucursal/'                          => ['page' => 1, 'page_size' => 1],
        'sucursal-paginado/'                 => ['page' => 1, 'page_size' => 1],
        'cargo/'                             => ['page' => 1, 'page_size' => 1],
        'cargo-paginado/'                    => ['page' => 1, 'page_size' => 1],
    ];

    public function handle(): int
    {
        $this->baseUrl = rtrim(config('services.talana.base_url', 'https://talana.com/es/api'), '/');
        $this->token   = config('services.talana.token', '');
        $this->headers = [
            'Authorization' => "Token {$this->token}",
            'Accept'        => 'application/json',
        ];
        $this->timeout = max(3, (int) ($this->option('timeout') ?? 10));

        $fecha = $this->option('fecha') ?: now()->subDay()->toDateString();

        $this->info('══════════════════════════════════════════════════════');
        $this->info('  Talana — Auditoría de Endpoints (evidencia acceso)');
        $this->info("  Fecha referencia: {$fecha}");
        $this->info("  Base URL:         {$this->baseUrl}");
        $this->info("  Timeout:          {$this->timeout}s por request");
        $this->info('══════════════════════════════════════════════════════');
        $this->newLine();

        $resultados = [];
        $total = count($this->endpoints);
        $i = 0;

        foreach ($this->endpoints as $path => $params) {
            $i++;
            // Agregar fecha a params que la usen
            $queryParams = $params;
            if (str_contains($path, 'turno') || str_contains($path, 'ausencia') ||
                str_contains($path, 'asistencia') || str_contains($path, 'schedule') ||
                str_contains($path, 'horario') || str_contains($path, 'mark') ||
                str_contains($path, 'worked') || str_contains($path, 'dayOff') ||
                str_contains($path, 'absence') || str_contains($path, 'persona') ||
                str_contains($path, 'schedule') || str_contains($path, 'assign') ||
                str_contains($path, 'programac')
            ) {
                $queryParams = array_merge(['desde' => $fecha, 'hasta' => $fecha, 'fecha' => $fecha], $queryParams);
            }

            [$status, $info] = $this->probe($path, $queryParams);
            $resultados[$path] = ['status' => $status, 'info' => $info];

            $sym    = $this->sym($status);
            $label  = $this->label($status);
            $this->line(sprintf("  %s [%s] %-45s %s", $sym, str_pad((string)$status, 3), $path, $label));
        }

        $this->newLine();
        $this->generarResumen($resultados, $fecha);
        $this->guardarReporte($resultados, $fecha);

        return self::SUCCESS;
    }

    // ─── Probe individual ──────────────────────────────────────────────────────

    private function probe(string $path, array $params): array
    {
        $url = "{$this->baseUrl}/{$path}";
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get($url, $params);

            $status = $response->status();
            $json   = $response->json();

            $info = [];
            if ($status === 200) {
                if (isset($json['count'])) {
                    $info['total'] = $json['count'];
                    if (!empty($json['results'][0])) {
                        $info['campos'] = array_keys($json['results'][0]);
                    }
                } elseif (is_array($json) && isset($json[0])) {
                    $info['total']  = count($json);
                    $info['campos'] = array_keys($json[0]);
                } elseif (is_array($json)) {
                    $info['campos'] = array_keys($json);
                }
            } elseif ($status === 403) {
                $info['mensaje'] = $json['detail'] ?? $json['message'] ?? substr((string) $response->body(), 0, 200);
            } elseif ($status === 404) {
                $info['mensaje'] = 'No encontrado';
            } else {
                $info['body'] = substr((string) $response->body(), 0, 300);
            }

            return [$status, $info];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [0, ['error' => 'TIMEOUT / conexión: ' . $e->getMessage()]];
        } catch (\Throwable $e) {
            return [-1, ['error' => $e->getMessage()]];
        }
    }

    // ─── Resumen por grupo ─────────────────────────────────────────────────────

    private function generarResumen(array $resultados, string $fecha): void
    {
        $grupos = [
            200 => [],
            403 => [],
            0   => [], // timeout
            404 => [],
            'otros' => [],
        ];

        foreach ($resultados as $path => $r) {
            $s = $r['status'];
            if ($s === 200) {
                $grupos[200][$path] = $r['info']['total'] ?? '?';
            } elseif ($s === 403) {
                $grupos[403][$path] = $r['info']['mensaje'] ?? '';
            } elseif ($s === 0) {
                $grupos[0][$path]   = $r['info']['error'] ?? 'timeout';
            } elseif ($s === 404) {
                $grupos[404][$path] = 'No existe';
            } else {
                $grupos['otros'][$path] = $s;
            }
        }

        // ── Accesibles ──
        if (! empty($grupos[200])) {
            $this->info('✅  ACCESIBLES (HTTP 200) — funcionando con el token actual:');
            foreach ($grupos[200] as $path => $total) {
                $this->line("     → {$path}" . ($total !== '?' ? "  ({$total} registros)" : ''));
            }
            $this->newLine();
        }

        // ── Bloqueados — principal evidencia ──
        if (! empty($grupos[403])) {
            $this->error('🚫  BLOQUEADOS (HTTP 403) — EXISTEN pero el token NO tiene permiso:');
            $this->line('     ↳ Estos son los endpoints a solicitar habilitación en Talana:');
            foreach ($grupos[403] as $path => $msg) {
                $this->line("     → {$this->baseUrl}/{$path}");
                if ($msg) {
                    $this->line("       Respuesta: {$msg}");
                }
            }
            $this->newLine();
        }

        // ── Timeout ──
        if (! empty($grupos[0])) {
            $this->warn('⏱️   TIMEOUT (sin respuesta en ' . $this->timeout . 's) — existen pero lentos:');
            foreach ($grupos[0] as $path => $err) {
                $this->line("     → {$path}");
            }
            $this->newLine();
        }

        // ── Otros errores ──
        if (! empty($grupos['otros'])) {
            $this->warn('⚠️   OTROS ERRORES:');
            foreach ($grupos['otros'] as $path => $status) {
                $this->line("     [{$status}] {$path}");
            }
            $this->newLine();
        }

        // ── Estadística ──
        $this->info('─────────────────────────────────────────────────────');
        $this->info('  RESUMEN EJECUTIVO (para solicitud a Talana):');
        $this->line('  Total endpoints sondeados:  ' . count($resultados));
        $this->line('  ✅ Accesibles (200):         ' . count($grupos[200]));
        $this->line('  🚫 Bloqueados (403):          ' . count($grupos[403]));
        $this->line('  ⏱  Timeout / lento:           ' . count($grupos[0]));
        $this->line('  ❌ No existen (404):          ' . count($grupos[404]));
        if (! empty($grupos['otros'])) {
            $this->line('  ⚠  Otros errores:             ' . count($grupos['otros']));
        }
        $this->info('─────────────────────────────────────────────────────');
    }

    // ─── Guardar reporte JSON ──────────────────────────────────────────────────

    private function guardarReporte(array $resultados, string $fecha): void
    {
        $archivo = "talana/auditoria_endpoints_{$fecha}.json";
        $reporte = [
            'fecha_auditoria' => now()->toDateTimeString(),
            'fecha_referencia' => $fecha,
            'base_url'        => $this->baseUrl,
            'token_preview'   => substr($this->token, 0, 8) . '...',
            'timeout_usado'   => $this->timeout,
            'resultados'      => $resultados,
            'resumen' => [
                'accesibles' => array_keys(array_filter($resultados, fn($r) => $r['status'] === 200)),
                'bloqueados' => array_keys(array_filter($resultados, fn($r) => $r['status'] === 403)),
                'timeout'    => array_keys(array_filter($resultados, fn($r) => $r['status'] === 0)),
                'no_existen' => array_keys(array_filter($resultados, fn($r) => $r['status'] === 404)),
            ],
        ];

        Storage::disk('local')->put($archivo, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("Reporte JSON guardado en: storage/app/private/{$archivo}");
    }

    // ─── Helpers de display ────────────────────────────────────────────────────

    private function sym(int $status): string
    {
        return match(true) {
            $status === 200             => '✅',
            $status === 403             => '🚫',
            $status === 0               => '⏱ ',
            $status === 404             => '  ',
            $status >= 500              => '💥',
            default                     => '⚠️',
        };
    }

    private function label(int $status): string
    {
        return match($status) {
            200  => 'ACCESIBLE',
            201  => 'ACCESIBLE (201)',
            403  => '← EXISTE, sin permiso → SOLICITAR ACCESO',
            404  => 'no existe',
            0    => 'TIMEOUT',
            500  => 'error servidor',
            default => "HTTP {$status}",
        };
    }
}
