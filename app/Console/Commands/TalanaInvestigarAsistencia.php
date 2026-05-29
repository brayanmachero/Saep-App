<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Comando de INVESTIGACIÓN (solo lectura).
 * Sondea los endpoints de asistencia de Talana para una fecha dada y guarda
 * la respuesta completa en JSON para inspección antes de construir la automatización.
 *
 * Uso:
 *   php artisan talana:investigar-asistencia            (fecha = ayer)
 *   php artisan talana:investigar-asistencia --fecha=2026-05-28
 *   php artisan talana:investigar-asistencia --registros=10
 */
class TalanaInvestigarAsistencia extends Command
{
    protected $signature = 'talana:investigar-asistencia
                            {--fecha=   : Fecha a investigar YYYY-MM-DD (default: ayer)}
                            {--registros=5 : Cantidad de registros de muestra a guardar}';

    protected $description = '[INVESTIGACIÓN] Sondea marcas, turnos y ausencias de Talana para una fecha dada';

    private string $baseUrl;
    private string $token;
    private array  $headers;
    private int    $timeout = 30;

    public function handle(): int
    {
        $this->baseUrl = rtrim(config('services.talana.base_url', 'https://talana.com/es/api'), '/');
        $this->token   = config('services.talana.token', '');
        $this->headers = [
            'Authorization' => "Token {$this->token}",
            'Accept'        => 'application/json',
        ];

        $fecha     = $this->option('fecha') ?: now()->subDay()->toDateString();
        $registros = max(1, (int) ($this->option('registros') ?? 5));

        $this->info("═══════════════════════════════════════════");
        $this->info("  Talana — Investigación Asistencia");
        $this->info("  Fecha:  {$fecha}");
        $this->info("  Token:  " . substr($this->token, 0, 8) . '...');
        $this->info("═══════════════════════════════════════════");

        $reporte = [
            'fecha_investigacion' => $fecha,
            'generado_en'         => now()->toDateTimeString(),
            'base_url'            => $this->baseUrl,
            'endpoints'           => [],
        ];

        // ─── 1. mark/ → Marcas de asistencia ─────────────────────────────────
        $this->line('');
        $this->line('1. Sondeando mark/ (marcas de asistencia)...');
        $reporte['endpoints']['mark'] = $this->probeEndpoint(
            'mark/',
            ['desde' => $fecha, 'hasta' => $fecha, 'page' => 1, 'page_size' => $registros],
            muestra: $registros
        );
        $this->reportResult($reporte['endpoints']['mark']);

        // ─── 2. rotativeDay-paginado/ → Turnos programados ───────────────────
        $this->line('');
        $this->line('2. Sondeando rotativeDay-paginado/ (turnos / rotación)...');
        // Intentar con filtros de fecha (puede aceptar o no)
        $reporte['endpoints']['rotativeDay_con_fecha'] = $this->probeEndpoint(
            'rotativeDay-paginado/',
            ['fecha' => $fecha, 'page' => 1, 'page_size' => $registros]
        );
        $this->reportResult($reporte['endpoints']['rotativeDay_con_fecha']);

        // También sin filtros para ver la estructura
        $this->line('   (también sin filtro de fecha)');
        $reporte['endpoints']['rotativeDay_sin_fecha'] = $this->probeEndpoint(
            'rotativeDay-paginado/',
            ['page' => 1, 'page_size' => $registros]
        );
        $this->reportResult($reporte['endpoints']['rotativeDay_sin_fecha']);

        // ─── 3. personaAusencia-paginado/ → Ausencias ────────────────────────
        $this->line('');
        $this->line('3. Sondeando personaAusencia-paginado/ (ausencias)...');
        $reporte['endpoints']['ausencias'] = $this->probeEndpoint(
            'personaAusencia-paginado/',
            ['fechaDesde' => $fecha, 'fechaHasta' => $fecha, 'page' => 1, 'page_size' => $registros]
        );
        $this->reportResult($reporte['endpoints']['ausencias']);

        // ─── 4. workedDays → Días trabajados ─────────────────────────────────
        $this->line('');
        $this->line('4. Sondeando workedDays...');
        $reporte['endpoints']['workedDays'] = $this->probeEndpoint(
            'workedDays',
            ['desde' => $fecha, 'hasta' => $fecha, 'page' => 1, 'page_size' => $registros]
        );
        $this->reportResult($reporte['endpoints']['workedDays']);

        // ─── 5. Endpoints adicionales de turnos que pueden existir ────────────
        $candidatosTurnos = [
            'turno-paginado/'       => ['page' => 1, 'page_size' => $registros],
            'turnos/'               => ['page' => 1, 'page_size' => $registros],
            'asignacion-turno/'     => ['fecha' => $fecha, 'page' => 1, 'page_size' => $registros],
            'jornada-paginado/'     => ['page' => 1, 'page_size' => $registros],
            'persona-turno/'        => ['fecha' => $fecha, 'page' => 1, 'page_size' => $registros],
            'scheduleDay-paginado/' => ['fecha' => $fecha, 'page' => 1, 'page_size' => $registros],
        ];

        $this->line('');
        $this->line('5. Buscando endpoints adicionales de turnos...');
        foreach ($candidatosTurnos as $path => $params) {
            $res = $this->probeEndpoint($path, $params);
            $reporte['endpoints']["extra_{$path}"] = $res;
            $status = $res['status'];
            $sym    = match(true) {
                $status >= 200 && $status < 300 => '✅',
                $status === 404 => '  ',
                default         => '⚠️',
            };
            $this->line("   {$sym} [{$status}] {$path}");
        }

        // ─── Guardar reporte ──────────────────────────────────────────────────
        $archivo = "talana/investigacion_asistencia_{$fecha}.json";
        Storage::disk('local')->put($archivo, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->info("Reporte guardado en: storage/app/private/{$archivo}");
        $this->info("Para ver: cat storage/app/private/{$archivo} | head -300");

        return self::SUCCESS;
    }

    // ─── HTTP helper ──────────────────────────────────────────────────────────

    private function probeEndpoint(string $path, array $query = [], int $muestra = 0): array
    {
        $url = "{$this->baseUrl}/{$path}";

        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get($url, $query);

            $json   = $response->json();
            $status = $response->status();

            // Extraer muestra de registros y estructura de campos
            $campos   = null;
            $ejemplos = [];
            $total    = null;

            if (is_array($json)) {
                // Paginado con 'results'
                if (isset($json['results']) && is_array($json['results'])) {
                    $total     = $json['count'] ?? count($json['results']);
                    $registros = $json['results'];
                    if (!empty($registros[0])) {
                        $campos   = array_keys($registros[0]);
                        $ejemplos = array_slice($registros, 0, max(1, $muestra));
                    }
                } elseif (isset($json[0])) {
                    // Array plano
                    $campos   = array_keys($json[0]);
                    $total    = count($json);
                    $ejemplos = array_slice($json, 0, max(1, $muestra));
                } else {
                    // Objeto/dict directo
                    $campos = array_keys($json);
                }
            }

            return [
                'status'        => $status,
                'url'           => $url,
                'query'         => $query,
                'total_api'     => $total,
                'campos'        => $campos,
                'ejemplos'      => $ejemplos,
                'respuesta_raw' => $muestra > 0 ? null : $json, // Guardar raw solo si no hay muestra
            ];

        } catch (\Throwable $e) {
            return [
                'status'  => 0,
                'url'     => $url,
                'query'   => $query,
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function reportResult(array $res): void
    {
        $status = $res['status'];
        $total  = $res['total_api'] ?? null;
        $campos = $res['campos']    ?? [];

        if ($status >= 200 && $status < 300) {
            $this->line("   ✅ [{$status}] Total: " . ($total ?? '?'));
            if ($campos) {
                $this->line("   Campos: " . implode(', ', $campos));
            }
            if (!empty($res['ejemplos'][0])) {
                $this->line("   1er registro:");
                foreach ($res['ejemplos'][0] as $k => $v) {
                    $vStr = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v;
                    $this->line("     {$k}: " . mb_substr($vStr, 0, 120));
                }
            }
        } elseif ($status === 404) {
            $this->line("   ⬜ [{$status}] Endpoint no existe");
        } elseif ($status === 401 || $status === 403) {
            $this->line("   🔒 [{$status}] Sin acceso");
        } else {
            $this->line("   ⚠️  [{$status}] " . ($res['error'] ?? 'Error inesperado'));
        }
    }
}
