<?php

namespace App\Console\Commands;

use App\Models\TalanaContrato;
use App\Models\TalanaMarca;
use App\Models\TalanaPersona;
use App\Services\TalanaService;
use App\Support\TalanaMarcaDirection;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza datos de la API Talana a tablas MySQL locales.
 * Estas tablas alimentan el dashboard de Grafana.
 *
 * Uso:
 *   php artisan talana:sync-db                      (personas + contratos + marcas mes actual)
 *   php artisan talana:sync-db --solo-contratos      (solo contratos + personas)
 *   php artisan talana:sync-db --meses=3             (marcas de los últimos 3 meses)
 *   php artisan talana:sync-db --dry-run             (solo muestra totales, no persiste)
 */
class TalanaSyncDbCommand extends Command
{
    protected $signature = 'talana:sync-db
                            {--solo-contratos : Sincroniza solo personas y contratos (omite marcas)}
                            {--meses=1        : Meses hacia atrás para sincronizar marcas (default: 1)}
                            {--dry-run        : Consulta la API y muestra totales, sin persistir}';

    protected $description = 'Sincroniza personas, contratos y marcas de Talana a MySQL para Grafana';

    public function __construct(private readonly TalanaService $talana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry = $this->option('dry-run');
        $meses = (int) $this->option('meses');
        $soloContr = $this->option('solo-contratos');

        // Marcar sync en curso (para el panel de estado del dashboard)
        if (! $isDry) {
            Cache::put('talana_sync_running', true, now()->addMinutes(15));
            Cache::put('talana_sync_started_at', now()->toDateTimeString(), now()->addHours(2));
            Cache::forget('talana_sync_error');
            Cache::forget('talana_sync_finished_at');
        }

        $this->info('─────────────────────────────────────────');
        $this->info('  Talana → MySQL Sync  '.($isDry ? '[DRY-RUN]' : ''));
        $this->info('─────────────────────────────────────────');

        try {
            // 1. Personas ─────────────────────────────────────────────────────
            $this->syncPersonas($isDry);

            // 2. Contratos ────────────────────────────────────────────────────
            $this->syncContratos($isDry);

            // 3. Marcas de asistencia ─────────────────────────────────────────
            if (! $soloContr) {
                $this->syncMarcas($isDry, $meses);
            }

            $this->info('');
            $this->info('✅ Sync completado'.($isDry ? ' (DRY-RUN — nada persistido)' : ''));

            if (! $isDry) {
                Cache::put('talana_sync_finished_at', now()->toDateTimeString(), now()->addHours(6));
                Cache::forget('talana_sync_error');
            }

        } catch (\Throwable $e) {
            $this->error('❌ Sync falló: '.$e->getMessage());
            if (! $isDry) {
                Cache::put('talana_sync_error', $e->getMessage(), now()->addHours(2));
            }

            return self::FAILURE;
        } finally {
            if (! $isDry) {
                Cache::forget('talana_sync_running');
            }
        }

        return self::SUCCESS;
    }

    // ─── Personas ─────────────────────────────────────────────────────────────

    private function syncPersonas(bool $isDry): void
    {
        $this->line('');
        $this->line('👤 Sincronizando personas...');

        try {
            $personas = $this->talana->personas();
            $this->line("   API: {$this->count($personas)} registros obtenidos");

            if ($isDry) {
                return;
            }

            $now = now();
            $lote = [];

            foreach ($personas as $p) {
                $lote[] = [
                    'talana_id' => $p['id'],
                    'rut' => $this->str($p['rut'] ?? null),
                    'nombre' => $this->str($p['name'] ?? $p['nombre'] ?? null),
                    'apellido_paterno' => $this->str($p['paternalSurname'] ?? $p['apellidoPaterno'] ?? null),
                    'apellido_materno' => $this->str($p['maternalSurname'] ?? $p['apellidoMaterno'] ?? null),
                    'email' => $this->str($p['email'] ?? null),
                    'fecha_nacimiento' => $this->date($p['birthDate'] ?? $p['fechaNacimiento'] ?? null),
                    'activo' => (bool) ($p['active'] ?? $p['activo'] ?? true),
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($lote)) {
                foreach (array_chunk($lote, 200) as $chunk) {
                    TalanaPersona::upsert($chunk, ['talana_id'], [
                        'rut', 'nombre', 'apellido_paterno', 'apellido_materno',
                        'email', 'fecha_nacimiento', 'activo', 'synced_at', 'updated_at',
                    ]);
                }
            }

            $this->line("   ✓ {$this->count($lote)} personas sincronizadas");

        } catch (\Exception $e) {
            $this->warn("   ⚠ Error personas: {$e->getMessage()}");
        }
    }

    // ─── Contratos ────────────────────────────────────────────────────────────

    private function syncContratos(bool $isDry): void
    {
        $this->line('');
        $this->line('📄 Sincronizando contratos...');

        $empresas = config('services.talana.empresas', []);

        if (empty($empresas)) {
            $this->warn('   ⚠ No hay empresas configuradas en services.talana.empresas');

            return;
        }

        try {
            $loteTotal = [];
            $now = now();

            foreach ($empresas as $empresaId => $empresaNombre) {
                $contratos = $this->talana->contratos(['empresa' => $empresaId]);
                $this->line("   {$empresaNombre} (ID {$empresaId}): {$this->count($contratos)} registros");

                if ($isDry) {
                    $tipos = array_count_values(array_column(
                        array_map(fn ($c) => ['t' => $c['tipoContratoDetails']['nombre'] ?? 'N/A'], $contratos),
                        't'
                    ));
                    foreach ($tipos as $tipo => $cnt) {
                        $this->line("     • {$tipo}: {$cnt}");
                    }

                    continue;
                }

                foreach ($contratos as $c) {
                    $emp = $c['empleadoDetails'] ?? [];
                    $tipo = $c['tipoContratoDetails'] ?? [];
                    $jef = $c['jefe'] ?? [];

                    $loteTotal[] = [
                        'talana_id' => $c['id'],
                        'empresa_id' => $empresaId,
                        'empresa_nombre' => $empresaNombre,
                        'persona_talana_id' => $emp['id'] ?? null,
                        'persona_nombre' => $this->nombreCompleto($emp),
                        'persona_rut' => $this->str($emp['rut'] ?? null),
                        'persona_email' => $this->str($emp['email'] ?? null),
                        'persona_fecha_nacimiento' => $this->date($emp['birthDate'] ?? $emp['fechaNacimiento'] ?? null),
                        'tipo_contrato' => $this->str($tipo['codigo'] ?? $tipo['id'] ?? null),
                        'tipo_contrato_nombre' => $this->str($tipo['nombre'] ?? null),
                        'fecha_contratacion' => $this->date($c['fechaContratacion'] ?? $c['hireDate'] ?? null),
                        'desde' => $this->date($c['desde'] ?? null),
                        'hasta' => $this->date($c['hasta'] ?? null),
                        'finiquitado' => (bool) ($c['finiquitado'] ?? false),
                        'sucursal_nombre' => $this->str($c['sucursal']['nombre'] ?? null),
                        'centro_costo_nombre' => $this->str($c['centroCosto']['nombre'] ?? null),
                        'cargo_nombre' => $this->str(is_string($c['cargo'] ?? null) ? ($c['cargo'] ?? null) : ($c['cargo']['nombre'] ?? $c['cargoDetails']['nombre'] ?? null)),
                        'horas_jornada' => isset($c['jornada']['horasDeLaJornada']) ? (float) $c['jornada']['horasDeLaJornada'] : null,
                        'jefe_nombre' => $this->nombreCompleto($jef),
                        'synced_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($isDry) {
                return;
            }

            if (! empty($loteTotal)) {
                foreach (array_chunk($loteTotal, 200) as $chunk) {
                    TalanaContrato::upsert($chunk, ['talana_id'], [
                        'empresa_id', 'empresa_nombre',
                        'persona_talana_id', 'persona_nombre', 'persona_rut', 'persona_email', 'persona_fecha_nacimiento',
                        'tipo_contrato', 'tipo_contrato_nombre', 'fecha_contratacion', 'desde', 'hasta',
                        'finiquitado', 'sucursal_nombre', 'centro_costo_nombre',
                        'cargo_nombre', 'horas_jornada', 'jefe_nombre', 'synced_at', 'updated_at',
                    ]);
                }
            }

            $activos = count(array_filter($loteTotal, fn ($c) => ! $c['finiquitado']));
            $finiquitados = count($loteTotal) - $activos;
            $this->line("   ✓ {$this->count($loteTotal)} contratos sincronizados ({$activos} activos, {$finiquitados} finiquitados)");

        } catch (\Exception $e) {
            $this->warn("   ⚠ Error contratos: {$e->getMessage()}");
        }
    }

    // ─── Marcas de asistencia ─────────────────────────────────────────────────

    private function syncMarcas(bool $isDry, int $meses): void
    {
        $this->line('');
        $this->line("🕐 Sincronizando marcas de asistencia ({$meses} mes(es))...");

        // Sync mes por mes para manejar memoria y timeout
        $hasta = Carbon::today();
        $desde = Carbon::today()->startOfMonth()->subMonths($meses - 1);

        $inicio = clone $desde;

        while ($inicio->lte($hasta)) {
            $fin = (clone $inicio)->endOfMonth()->min($hasta);
            $desdeStr = $inicio->format('Y-m-d');
            $hastaStr = $fin->format('Y-m-d');

            $this->line("   📅 {$desdeStr} → {$hastaStr}");

            try {
                $marcas = [];
                $empresas = config('services.talana.empresas', []);

                if (empty($empresas)) {
                    $marcas = $this->talana->marcasAsistencia($desdeStr, $hastaStr, 90);
                } else {
                    foreach ($empresas as $empresaId => $empresaNombre) {
                        $loteEmpresa = $this->talana->marcasAsistencia($desdeStr, $hastaStr, 90, (int) $empresaId);
                        $this->line("      {$empresaNombre}: {$this->count($loteEmpresa)} marcas");
                        $marcas = array_merge($marcas, $loteEmpresa);
                    }
                }
                $this->line("      API: {$this->count($marcas)} marcas obtenidas");

                if (! $isDry && ! empty($marcas)) {
                    // Cargar mapa persona_id → nombre/rut/cc desde contratos locales
                    $personaMap = $this->buildPersonaMap();

                    $lote = [];
                    $now = now();

                    foreach ($marcas as $m) {
                        $pid = $m['person']['id'] ?? ($m['personId'] ?? null);
                        $tsRaw = $m['markedAt'] ?? ($m['TS'] ?? ($m['fecha'] ?? null));
                        $infoCC = $personaMap[$pid] ?? [];

                        if (! $pid || ! $tsRaw) {
                            continue;
                        }

                        try {
                            $ts = Carbon::parse($tsRaw);
                        } catch (\Exception) {
                            continue;
                        }

                        $personData = $m['person'] ?? [];

                        $lote[] = [
                            'persona_talana_id' => $pid,
                            'persona_nombre' => $infoCC['nombre'] ?? $this->nombreCompleto($personData),
                            'persona_rut' => $infoCC['rut'] ?? $this->str($personData['rut'] ?? null),
                            'fecha' => $ts->toDateString(),
                            'hora' => $ts->toTimeString(),
                            'tipo' => TalanaMarcaDirection::normalize($m['direction'] ?? $m['tipo'] ?? null),
                            'centro_costo_nombre' => $infoCC['cc'] ?? null,
                            'raw_ts' => $ts->toDateTimeString(),
                            'synced_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (! empty($lote)) {
                        foreach (array_chunk($lote, 500) as $chunk) {
                            TalanaMarca::upsert($chunk, ['persona_talana_id', 'raw_ts'], [
                                'persona_nombre', 'persona_rut', 'fecha', 'hora',
                                'tipo', 'centro_costo_nombre', 'synced_at', 'updated_at',
                            ]);
                        }
                    }

                    $this->line("      ✓ {$this->count($lote)} marcas sincronizadas");
                }

            } catch (\Exception $e) {
                $this->warn("      ⚠ Error: {$e->getMessage()}");
            }

            $inicio->addMonth()->startOfMonth();
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildPersonaMap(): array
    {
        return TalanaContrato::query()
            ->where('finiquitado', false)
            ->select('persona_talana_id', 'persona_nombre', 'persona_rut', 'centro_costo_nombre')
            ->get()
            ->keyBy('persona_talana_id')
            ->map(fn ($c) => [
                'nombre' => $c->persona_nombre,
                'rut' => $c->persona_rut,
                'cc' => $c->centro_costo_nombre,
            ])
            ->toArray();
    }

    private function nombreCompleto(array $data): ?string
    {
        $partes = array_filter([
            $data['name'] ?? $data['nombre'] ?? null,
            $data['paternalSurname'] ?? $data['apellidoPaterno'] ?? null,
            $data['maternalSurname'] ?? $data['apellidoMaterno'] ?? null,
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
