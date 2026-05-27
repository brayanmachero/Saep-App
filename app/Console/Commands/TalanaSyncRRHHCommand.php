<?php

namespace App\Console\Commands;

use App\Models\TalanaAusencia;
use App\Models\TalanaSaldoVacaciones;
use App\Services\TalanaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza datos de RRHH de la API Talana a tablas MySQL locales.
 *   - Saldo de vacaciones por empleado (snapshot completo)
 *   - Ausencias / licencias / permisos (incremental por fecha)
 *
 * Uso:
 *   php artisan talana:sync-rrhh                     (vacaciones + ausencias últimos 12 meses)
 *   php artisan talana:sync-rrhh --solo-vacaciones    (solo saldo de vacaciones)
 *   php artisan talana:sync-rrhh --solo-ausencias     (solo ausencias)
 *   php artisan talana:sync-rrhh --meses=24           (ausencias de los últimos 24 meses)
 *   php artisan talana:sync-rrhh --dry-run            (muestra totales, no persiste)
 */
class TalanaSyncRRHHCommand extends Command
{
    protected $signature = 'talana:sync-rrhh
                            {--solo-vacaciones : Sincroniza solo saldo de vacaciones}
                            {--solo-ausencias  : Sincroniza solo ausencias}
                            {--meses=12        : Meses hacia atrás para sincronizar ausencias (default: 12)}
                            {--dry-run         : Consulta la API y muestra totales, sin persistir}';

    protected $description = 'Sincroniza saldo de vacaciones y ausencias de Talana a MySQL';

    public function __construct(private readonly TalanaService $talana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry         = $this->option('dry-run');
        $meses         = (int) $this->option('meses');
        $soloVacaciones = $this->option('solo-vacaciones');
        $soloAusencias  = $this->option('solo-ausencias');

        if (! $isDry) {
            Cache::put('talana_rrhh_sync_running', true, now()->addMinutes(30));
            Cache::put('talana_rrhh_sync_started_at', now()->toDateTimeString(), now()->addHours(2));
            Cache::forget('talana_rrhh_sync_error');
            Cache::forget('talana_rrhh_sync_finished_at');
        }

        $this->info('─────────────────────────────────────────────');
        $this->info('  Talana RRHH → MySQL Sync  ' . ($isDry ? '[DRY-RUN]' : ''));
        $this->info('─────────────────────────────────────────────');

        try {
            if (! $soloAusencias) {
                $this->syncVacaciones($isDry);
            }

            if (! $soloVacaciones) {
                $this->syncAusencias($isDry, $meses);
            }

            $this->info('');
            $this->info('✅ Sync RRHH completado' . ($isDry ? ' (DRY-RUN — nada persistido)' : ''));

            if (! $isDry) {
                Cache::put('talana_rrhh_sync_finished_at', now()->toDateTimeString(), now()->addHours(6));
                Cache::forget('talana_rrhh_sync_error');
            }

        } catch (\Throwable $e) {
            $this->error('❌ Sync RRHH falló: ' . $e->getMessage());
            if (! $isDry) {
                Cache::put('talana_rrhh_sync_error', $e->getMessage(), now()->addHours(2));
            }
            return self::FAILURE;
        } finally {
            if (! $isDry) {
                Cache::forget('talana_rrhh_sync_running');
            }
        }

        return self::SUCCESS;
    }

    // ─── Saldo de vacaciones ──────────────────────────────────────────────────

    private function syncVacaciones(bool $isDry): void
    {
        $this->line('');
        $this->line('🏖️  Sincronizando saldo de vacaciones...');

        try {
            $registros = $this->talana->saldoVacaciones();
            $this->line("   API: {$this->count($registros)} empleados obtenidos");

            if ($isDry) {
                $conSaldo = count(array_filter($registros, fn($r) => ($r['saldo']['diasRestantes'] ?? 0) > 0));
                $this->line("   • Con saldo pendiente: {$conSaldo}");
                return;
            }

            $now  = now();
            $lote = [];

            foreach ($registros as $r) {
                $saldo  = $r['saldo'] ?? [];
                $lote[] = [
                    'empleado_id'      => $r['empleado_id'],
                    'rut'              => $this->str($r['rut'] ?? null),
                    'nombre'           => $this->str($r['nombre'] ?? null),
                    'fecha_corte'      => $this->date($r['fecha_corte'] ?? null),
                    'dias_normales'    => (float) ($saldo['diasNormales'] ?? 0),
                    'dias_progresivos' => (float) ($saldo['diasProgresivos'] ?? 0),
                    'dias_restantes'   => (float) ($saldo['diasRestantes'] ?? 0),
                    'dias_zona_extrema'=> (float) ($saldo['diasZonaExtrema'] ?? 0),
                    'tiene_error'      => ! empty($r['error']),
                    'synced_at'        => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (! empty($lote)) {
                // Snapshot completo: truncar y reinsertar
                DB::table('talana_saldo_vacaciones')->truncate();
                foreach (array_chunk($lote, 200) as $chunk) {
                    TalanaSaldoVacaciones::insert($chunk);
                }
            }

            $conSaldo = count(array_filter($lote, fn($r) => $r['dias_restantes'] > 0));
            $this->line("   ✓ {$this->count($lote)} registros sincronizados ({$conSaldo} con saldo pendiente)");

        } catch (\Exception $e) {
            $this->warn("   ⚠ Error vacaciones: {$e->getMessage()}");
        }
    }

    // ─── Ausencias ────────────────────────────────────────────────────────────

    private function syncAusencias(bool $isDry, int $meses): void
    {
        $this->line('');
        $this->line("📋 Sincronizando ausencias (últimos {$meses} mes(es))...");

        try {
            $desde = Carbon::now()->subMonths($meses)->startOfMonth()->format('Y-m-d');
            $hasta = Carbon::today()->format('Y-m-d');

            $this->line("   Rango: {$desde} → {$hasta}");

            $ausencias = $this->talana->ausencias($desde, $hasta);
            $this->line("   API: {$this->count($ausencias)} registros obtenidos");

            if ($isDry) {
                $tipos = array_count_values(
                    array_map(fn($a) => $a['tipoAusencia'] ?? 'N/A', $ausencias)
                );
                arsort($tipos);
                foreach ($tipos as $tipo => $cnt) {
                    $this->line("     • {$tipo}: {$cnt}");
                }
                return;
            }

            $now  = now();
            $lote = [];

            foreach ($ausencias as $a) {
                $det = $a['detallesTrabajador'] ?? [];

                $lote[] = [
                    'talana_id'       => $a['id'],
                    'empleado_id'     => $a['empleado'],
                    'persona_rut'     => $this->str($det['rut'] ?? null),
                    'persona_nombre'  => $this->buildNombre($det),
                    'tipo_ausencia'   => $this->str($a['tipoAusencia'] ?? null),
                    'fecha_desde'     => $this->date($a['fechaDesde'] ?? null),
                    'fecha_hasta'     => $this->date($a['fechaHasta'] ?? null),
                    'numero_dias'     => isset($a['numeroDias']) ? (int) $a['numeroDias'] : null,
                    'aprobada'        => (bool) ($a['aprobada'] ?? false),
                    'rebaja_salario'  => (bool) ($a['rebajaSalario'] ?? false),
                    'es_continuacion' => (bool) ($a['esContinuacion'] ?? false),
                    'fecha_retorno'   => $this->date($a['fechaRetorno'] ?? null),
                    'numero_licencia' => $this->str($a['numeroLicencia'] ?? null),
                    'synced_at'       => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if (! empty($lote)) {
                foreach (array_chunk($lote, 200) as $chunk) {
                    TalanaAusencia::upsert($chunk, ['talana_id'], [
                        'empleado_id', 'persona_rut', 'persona_nombre', 'tipo_ausencia',
                        'fecha_desde', 'fecha_hasta', 'numero_dias', 'aprobada',
                        'rebaja_salario', 'es_continuacion', 'fecha_retorno',
                        'numero_licencia', 'synced_at', 'updated_at',
                    ]);
                }
            }

            $this->line("   ✓ {$this->count($lote)} ausencias sincronizadas");

        } catch (\Exception $e) {
            $this->warn("   ⚠ Error ausencias: {$e->getMessage()}");
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildNombre(array $data): ?string
    {
        $partes = array_filter([
            $data['nombre'] ?? null,
            $data['apellidoPaterno'] ?? null,
            $data['apellidoMaterno'] ?? null,
        ]);
        return $partes ? trim(implode(' ', $partes)) : null;
    }

    private function str(?string $val): ?string
    {
        if ($val === null || trim($val) === '') {
            return null;
        }
        return mb_substr(trim($val), 0, 200);
    }

    private function date(?string $val): ?string
    {
        if (! $val) {
            return null;
        }
        try {
            return Carbon::parse($val)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    private function count(array $arr): int
    {
        return count($arr);
    }
}
