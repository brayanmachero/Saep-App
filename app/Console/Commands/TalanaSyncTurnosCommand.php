<?php

namespace App\Console\Commands;

use App\Models\TalanaAssignationSummary;
use App\Models\TalanaWorkShiftPersonRange;
use App\Services\TalanaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza datos de turnos y jornada calculada de Talana a MySQL.
 *
 *   talana_work_shift_person_ranges  — Asignaciones persona ↔ turno (snapshot completo)
 *   talana_assignation_summaries     — Jornada calculada por día (incremental por rango de fechas)
 *
 * Uso:
 *   php artisan talana:sync-turnos                    (ambos, últimos 30 días de jornada)
 *   php artisan talana:sync-turnos --solo-turnos      (solo workShiftPersonRange)
 *   php artisan talana:sync-turnos --solo-jornadas    (solo assignationSummaryApi)
 *   php artisan talana:sync-turnos --dias=60          (jornada de los últimos 60 días)
 *   php artisan talana:sync-turnos --dry-run          (muestra totales sin persistir)
 */
class TalanaSyncTurnosCommand extends Command
{
    protected $signature = 'talana:sync-turnos
                            {--solo-turnos   : Sincroniza solo asignaciones persona ↔ turno}
                            {--solo-jornadas : Sincroniza solo jornada calculada (assignationSummary)}
                            {--dias=30       : Días hacia atrás para sincronizar jornada calculada}
                            {--dry-run       : Consulta la API y muestra totales, sin persistir}';

    protected $description = 'Sincroniza turnos (workShiftPersonRange) y jornada calculada (assignationSummary) de Talana a MySQL';

    public function __construct(private readonly TalanaService $talana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry         = $this->option('dry-run');
        $dias          = (int) $this->option('dias');
        $soloTurnos    = $this->option('solo-turnos');
        $soloJornadas  = $this->option('solo-jornadas');

        if (! $isDry) {
            Cache::put('talana_turnos_sync_running', true, now()->addMinutes(30));
            Cache::put('talana_turnos_sync_started_at', now()->toDateTimeString(), now()->addHours(2));
            Cache::forget('talana_turnos_sync_error');
            Cache::forget('talana_turnos_sync_finished_at');
        }

        $this->info('─────────────────────────────────────────────────');
        $this->info('  Talana Turnos → MySQL Sync  ' . ($isDry ? '[DRY-RUN]' : ''));
        $this->info('─────────────────────────────────────────────────');

        try {
            if (! $soloJornadas) {
                $this->syncWorkShiftPersonRange($isDry);
            }

            if (! $soloTurnos) {
                $this->syncAssignationSummary($isDry, $dias);
            }

            $this->info('');
            $this->info('✅ Sync turnos completado' . ($isDry ? ' (DRY-RUN — nada persistido)' : ''));

            if (! $isDry) {
                Cache::put('talana_turnos_sync_finished_at', now()->toDateTimeString(), now()->addHours(6));
                Cache::forget('talana_turnos_sync_error');
            }

        } catch (\Throwable $e) {
            $this->error('❌ Sync turnos falló: ' . $e->getMessage());
            if (! $isDry) {
                Cache::put('talana_turnos_sync_error', $e->getMessage(), now()->addHours(2));
            }
            return self::FAILURE;
        } finally {
            if (! $isDry) {
                Cache::forget('talana_turnos_sync_running');
            }
        }

        return self::SUCCESS;
    }

    // ─── workShiftPersonRange: asignación persona ↔ turno ────────────────────

    private function syncWorkShiftPersonRange(bool $isDry): void
    {
        $this->line('');
        $this->line('🔄 Sincronizando asignaciones persona ↔ turno (workShiftPersonRange)...');

        try {
            $registros = $this->talana->workShiftPersonRange();
            $total     = count($registros);
            $this->line("   API: {$total} registros obtenidos");

            if ($isDry) {
                // Muestra algunos ejemplos
                $muestra = array_slice($registros, 0, 3);
                foreach ($muestra as $r) {
                    $this->line("   • person={$r['person']} workShift={$r['workShift']} from={$r['fromDate']} to={$r['toDate']}");
                }
                return;
            }

            if (empty($registros)) {
                $this->warn('   ⚠ Sin registros — nada que persistir');
                return;
            }

            $now  = now();
            $lote = [];

            foreach ($registros as $r) {
                $toDate = $r['toDate'] ?? null;
                // Normalizar la fecha "indefinida" 2099-01-01 → null
                if ($toDate && str_starts_with($toDate, '2099')) {
                    $toDate = null;
                }

                $lote[] = [
                    'talana_id'         => $r['id'],
                    'persona_talana_id' => $r['person'],
                    'work_shift_id'     => $r['workShift'],
                    'from_date'         => $r['fromDate'],
                    'to_date'           => $toDate,
                    'synced_at'         => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            // Snapshot completo: vaciar y reinsertar (los rangos pueden cambiar)
            DB::table('talana_work_shift_person_ranges')->truncate();

            foreach (array_chunk($lote, 500) as $chunk) {
                TalanaWorkShiftPersonRange::insert($chunk);
            }

            $this->line("   ✓ {$total} asignaciones sincronizadas");

        } catch (\Exception $e) {
            $this->warn("   ⚠ Error workShiftPersonRange: {$e->getMessage()}");
            throw $e;
        }
    }

    // ─── assignationSummaryApi: jornada calculada por día ─────────────────────

    private function syncAssignationSummary(bool $isDry, int $dias): void
    {
        $this->line('');
        $this->line("📅 Sincronizando jornada calculada (assignationSummaryApi, últimos {$dias} días)...");

        $hasta  = Carbon::today();
        $desde  = Carbon::today()->subDays($dias);
        $desdeStr = $desde->toDateString();
        $hastaStr = $hasta->toDateString();

        $this->line("   Rango: {$desdeStr} → {$hastaStr}");

        try {
            $registros = $this->talana->assignationSummary($desdeStr, $hastaStr);
            $totalFiltrado = count($registros);
            $this->line("   Registros en rango: {$totalFiltrado}");

            if ($isDry) {
                $diasDescanso = count(array_filter($registros, fn($r) => ! ($r['workingDay'] ?? true)));
                $diasTrabajo  = $totalFiltrado - $diasDescanso;
                $this->line("   • Días laborables: {$diasTrabajo}");
                $this->line("   • Días de descanso (workingDay=false): {$diasDescanso}");
                return;
            }

            if (empty($registros)) {
                $this->warn('   ⚠ Sin registros en rango — nada que persistir');
                return;
            }

            $now  = now();
            $lote = [];

            foreach ($registros as $r) {
                $person  = $r['person'] ?? [];
                $personId = is_array($person) ? ($person['id'] ?? null) : $person;

                if (! $personId) {
                    continue;
                }

                $nombre  = null;
                $rut     = null;
                if (is_array($person)) {
                    $partes = array_filter([
                        $person['nombre']          ?? null,
                        $person['apellidoPaterno'] ?? null,
                        $person['apellidoMaterno'] ?? null,
                    ]);
                    $nombre = $partes ? mb_substr(trim(implode(' ', $partes)), 0, 200) : null;
                    $rut    = $this->str($person['rut'] ?? null);
                }

                $lote[] = [
                    'talana_id'         => $r['id'],
                    'persona_talana_id' => $personId,
                    'persona_rut'       => $rut,
                    'persona_nombre'    => $nombre,
                    'fecha'             => $r['date'],
                    'working_day'       => (bool) ($r['workingDay'] ?? true),
                    'absence_type'      => $this->str($r['absenceType'] ?? null),
                    'status'            => $this->str($r['status'] ?? null),
                    'entrance_datetime' => $this->dt($r['entranceDateTime'] ?? null),
                    'exit_datetime'     => $this->dt($r['exitDateTime'] ?? null),
                    'working_seconds'   => isset($r['workingSeconds']) ? (int) $r['workingSeconds'] : null,
                    'delay_seconds'     => isset($r['delaySeconds']) ? (int) $r['delaySeconds'] : null,
                    'synced_at'         => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if (! empty($lote)) {
                foreach (array_chunk($lote, 500) as $chunk) {
                    TalanaAssignationSummary::upsert(
                        $chunk,
                        ['persona_talana_id', 'fecha'],
                        [
                            'talana_id', 'persona_rut', 'persona_nombre',
                            'working_day', 'absence_type', 'status',
                            'entrance_datetime', 'exit_datetime',
                            'working_seconds', 'delay_seconds',
                            'synced_at', 'updated_at',
                        ]
                    );
                }
            }

            $descansos = count(array_filter($lote, fn($r) => ! $r['working_day']));
            $this->line("   ✓ {$totalFiltrado} jornadas sincronizadas ({$descansos} días de descanso)");

        } catch (\Exception $e) {
            $this->warn("   ⚠ Error assignationSummaryApi: {$e->getMessage()}");
            throw $e;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function str(?string $val): ?string
    {
        if ($val === null || trim($val) === '') {
            return null;
        }
        return mb_substr(trim($val), 0, 200);
    }

    private function dt(?string $val): ?string
    {
        if (! $val) {
            return null;
        }
        try {
            return Carbon::parse($val)->toDateTimeString();
        } catch (\Exception) {
            return null;
        }
    }
}
