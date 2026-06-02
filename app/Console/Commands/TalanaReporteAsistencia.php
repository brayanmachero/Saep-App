<?php

namespace App\Console\Commands;

use App\Mail\TalanaAsistenciaReporteMail;
use App\Models\TalanaContrato;
use App\Models\TalanaMarca;
use App\Services\TalanaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Genera y envía el reporte diario de asistencia Talana por email.
 *
 * Uso:
 *   php artisan talana:reporte-asistencia                         (ayer, envía email)
 *   php artisan talana:reporte-asistencia --fecha=2026-05-28      (fecha específica)
 *   php artisan talana:reporte-asistencia --dry-run               (muestra resumen, no envía)
 *   php artisan talana:reporte-asistencia --email=otro@saep.cl    (destinatario override)
 *   php artisan talana:reporte-asistencia --dias-nuevo=60         (umbral días para "nuevo")
 */
class TalanaReporteAsistencia extends Command
{
    protected $signature = 'talana:reporte-asistencia
                            {--fecha=           : Fecha YYYY-MM-DD a analizar (default: ayer)}
                            {--email=           : Email destinatario (default: TALANA_ALERTA_EMAIL)}
                            {--dias-nuevo=60    : Días de antigüedad máxima para marcar como "nuevo"}
                            {--jornada-normal=9 : Horas de jornada normal para calcular exceso (default: 9)}
                            {--horas-extras-max=7 : Horas extras sobre la jornada normal que disparan revisión (default: 7)}
                            {--dry-run          : Muestra resumen por consola sin enviar email}';

    protected $description = 'Genera el reporte diario de asistencia Talana y lo envía por email';

    public function __construct(private readonly TalanaService $talana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha     = $this->option('fecha') ?: Carbon::yesterday()->toDateString();
        $isDry     = $this->option('dry-run');
        $diasNuevo = (int) ($this->option('dias-nuevo') ?? 60);
        $email     = $this->option('email') ?: config('services.talana.alerta_email');

        $jornadaNormal  = (float) ($this->option('jornada-normal')   ?? 9);
        $horasExtrasMax = (float) ($this->option('horas-extras-max') ?? 7);
        $umbralAltoH    = $jornadaNormal + $horasExtrasMax; // ej. 9+7 = 16h → sospechoso
        $umbralBajoH    = 7.0;                             // < 7h trabajadas → sospechoso

        if (! $email) {
            $this->error('No hay email configurado. Usa --email= o define TALANA_ALERTA_EMAIL en .env');
            return self::FAILURE;
        }

        $this->info("═══════════════════════════════════════════");
        $this->info("  Talana — Reporte Asistencia: {$fecha}");
        $this->info("═══════════════════════════════════════════");

        // ─── 1. Obtener marcas del día desde la API ───────────────────────────
        $this->line('');
        $this->line('📡 Obteniendo marcas de asistencia...');

        try {
            $marcasRaw = $this->talana->marcasAsistencia($fecha, $fecha, 60);
        } catch (\Throwable $e) {
            $this->error("Error al obtener marcas: {$e->getMessage()}");
            Log::error('TalanaReporteAsistencia: error marcas API', ['error' => $e->getMessage(), 'fecha' => $fecha]);
            return self::FAILURE;
        }

        $this->line("   ✓ {$this->cnt($marcasRaw)} marcas recibidas");

        // ─── 2. Agrupar marcas por persona ────────────────────────────────────
        $marcasPorPersona = $this->agruparMarcas($marcasRaw, $fecha);

        // ─── 3. Cargar trabajadores activos desde DB local ────────────────────
        // (sincronizados en el talana:sync-db diario de las 06:00)
        $this->line('');
        $this->line('🗄️  Cargando trabajadores activos desde DB local...');

        $contratosActivos = TalanaContrato::query()
            ->where('finiquitado', false)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('hasta')
                  ->orWhere('hasta', '>=', $fecha);
            })
            ->where('desde', '<=', $fecha)
            ->get(['talana_id', 'persona_talana_id', 'persona_nombre', 'persona_rut',
                   'centro_costo_nombre', 'sucursal_nombre', 'tipo_contrato_nombre',
                   'cargo_nombre', 'desde', 'hasta', 'empresa_id', 'empresa_nombre']);

        $this->line("   ✓ {$this->cnt($contratosActivos->toArray())} contratos activos para {$fecha}");

        // ─── 4. Obtener jornada calculada del día directamente desde la API Talana ──
        // Se consulta en tiempo real (no depende de talana:sync-turnos) para garantizar
        // que los datos de ayer estén disponibles cuando se genera el reporte a las 08:15.
        // Permite distinguir días de descanso (workingDay=false) y detectar horas anómalas.
        $this->line('');
        $this->line('📡 Obteniendo jornada calculada (assignationSummary) de la API...');

        $jornadaPorPersona = []; // pid → bool (true=día laboral, false=día de descanso)
        $horasAsignacion   = []; // pid → working_seconds (int)

        try {
            $assignSummaries = $this->talana->assignationSummary($fecha, $fecha, 120);

            if (empty($assignSummaries)) {
                $this->warn('   ⚠ Sin datos de jornada en API para ' . $fecha . '. El filtro de días de descanso y horas no estará disponible.');
            } else {
                foreach ($assignSummaries as $rec) {
                    $person   = $rec['person'] ?? [];
                    $personId = is_array($person) ? ($person['id'] ?? null) : $person;
                    if (! $personId) {
                        continue;
                    }
                    $jornadaPorPersona[$personId] = (bool) ($rec['workingDay'] ?? true);
                    if (isset($rec['workingSeconds']) && $rec['workingSeconds'] !== null) {
                        $horasAsignacion[$personId] = (int) $rec['workingSeconds'];
                    }
                }
                $totalDescansos = count(array_filter($jornadaPorPersona, fn($v) => $v === false));
                $this->line('   ✓ ' . count($assignSummaries) . " jornadas cargadas ({$totalDescansos} días de descanso)");
            }
        } catch (\Throwable $e) {
            $this->warn('   ⚠ No se pudo cargar jornada calculada: ' . $e->getMessage());
        }

        // ─── 5. Analizar cada trabajador activo ───────────────────────────
        $resultado = $this->analizarTrabajadores(
            $contratosActivos,
            $marcasPorPersona,
            $jornadaPorPersona,
            $fecha,
            $diasNuevo,
            $horasAsignacion,
            $umbralAltoH,
            $umbralBajoH
        );

        // ─── 6. Mostrar resumen por consola ───────────────────────────────────
        $this->mostrarResumen($resultado, $fecha);

        // ─── 7. Enviar email (salvo dry-run) ─────────────────────────────────
        if ($isDry) {
            $this->line('');
            $this->warn('[DRY-RUN] Email no enviado');
            return self::SUCCESS;
        }

        if ($resultado['total_incompletas'] === 0
            && $resultado['total_sin_marcacion'] === 0
            && $resultado['total_sin_enrolar'] === 0) {
            $this->line('');
            $this->info('✅ Sin anomalías — email informativo igualmente enviado');
        }

        try {
            Mail::to($email)->send(new TalanaAsistenciaReporteMail($resultado, $fecha));
            $this->info("📧 Email enviado a {$email}");
        } catch (\Throwable $e) {
            $this->error("Error al enviar email: {$e->getMessage()}");
            Log::error('TalanaReporteAsistencia: error email', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ─── Agrupar marcas por persona ───────────────────────────────────────────

    /**
     * Recibe el array plano de marcas de la API y devuelve un mapa
     * persona_id → ['persona' => [...], 'marcas' => [[ts, dir], ...], 'categoria' => '...']
     */
    private function agruparMarcas(array $marcasRaw, string $fecha): array
    {
        $mapa = [];

        foreach ($marcasRaw as $m) {
            $personId = $m['person']['id'] ?? null;
            if (! $personId) {
                continue;
            }

            if (! isset($mapa[$personId])) {
                $mapa[$personId] = [
                    'persona' => [
                        'id'     => $personId,
                        'nombre' => trim(implode(' ', array_filter([
                            $m['person']['nombre']        ?? null,
                            $m['person']['apellidoPaterno'] ?? null,
                            $m['person']['apellidoMaterno'] ?? null,
                        ]))),
                        'rut'    => $m['person']['rut'] ?? null,
                    ],
                    'marcas' => [],
                ];
            }

            try {
                $ts  = Carbon::parse($m['TS'])->setTimezone('America/Santiago');
                $dir = strtoupper(trim($m['direction'] ?? ''));
            } catch (\Exception) {
                continue;
            }

            $mapa[$personId]['marcas'][] = [
                'ts'        => $ts,
                'ts_str'    => $ts->format('H:i:s'),
                'direction' => $dir,
            ];
        }

        // Ordenar marcas por hora y categorizar
        foreach ($mapa as $pid => &$data) {
            usort($data['marcas'], fn($a, $b) => $a['ts']->getTimestamp() <=> $b['ts']->getTimestamp());
            $data['categoria'] = $this->categorizarMarcas($data['marcas']);
        }
        unset($data);

        return $mapa;
    }

    /**
     * Categoriza las marcas de un trabajador en un día:
     * - completo       → tiene al menos 1 E y 1 S
     * - solo_entrada   → solo tiene E (no salió)
     * - solo_salida    → solo tiene S (entró pero no registró entrada)
     * - multiple       → más de 2 marcas (puede ser turno partido)
     * - sin_datos      → 0 marcas (no debería llegar aquí)
     */
    private function categorizarMarcas(array $marcas): string
    {
        if (empty($marcas)) {
            return 'sin_datos';
        }

        $dirs = array_column($marcas, 'direction');
        $tieneE = in_array('E', $dirs, true);
        $tieneS = in_array('S', $dirs, true);

        if (count($marcas) > 2) {
            return 'multiple';
        }

        if ($tieneE && $tieneS) {
            return 'completo';
        }

        if ($tieneE && ! $tieneS) {
            return 'solo_entrada';
        }

        if (! $tieneE && $tieneS) {
            return 'solo_salida';
        }

        return 'incompleto'; // 2 E o 2 S
    }

    // ─── Analizar trabajadores activos ────────────────────────────────────────

    private function analizarTrabajadores(
        $contratosActivos,
        array $marcasPorPersona,
        array $jornadaPorPersona,
        string $fecha,
        int $diasNuevo,
        array $horasAsignacion = [],
        float $umbralAltoH = 16.0,
        float $umbralBajoH = 7.0
    ): array {
        $completos        = [];
        $incompletas      = [];
        $sinMarcacion     = [];
        $probablesNuevos  = [];
        $descanso         = []; // Día de descanso según turno (workingDay = false)
        $revision         = []; // Anomalías que requieren revisión manual

        foreach ($contratosActivos as $contrato) {
            $pid = $contrato->persona_talana_id;

            if (isset($marcasPorPersona[$pid])) {
                $data = $marcasPorPersona[$pid];
                $fila = $this->buildFilaTrabajador($contrato, $data['marcas'], $data['categoria']);

                // Si marcó pero su jornada es día de descanso → requiere revisión
                $esDescansoConMarca = isset($jornadaPorPersona[$pid]) && $jornadaPorPersona[$pid] === false;

                if ($esDescansoConMarca) {
                    $revision[] = array_merge($fila, [
                        'categoria' => 'revision',
                        'motivo'    => 'Marcó en día de descanso',
                    ]);
                } else {
                    switch ($data['categoria']) {
                        case 'completo':
                        case 'multiple':
                            // ── Detección de horas anómalas ──────────────────────
                            $horas = $this->resolverHorasTrabajadas($pid, $horasAsignacion, $fila);
                            if ($horas !== null && $horas > $umbralAltoH) {
                                $revision[] = array_merge($fila, [
                                    'categoria' => 'revision',
                                    'motivo'    => sprintf(
                                        'Horas excesivas: %.1fh trabajadas (máx. %.0fh por día)',
                                        $horas, $umbralAltoH
                                    ),
                                    'horas_trabajadas' => $horas,
                                ]);
                            } elseif ($horas !== null && $horas < $umbralBajoH) {
                                $revision[] = array_merge($fila, [
                                    'categoria' => 'revision',
                                    'motivo'    => sprintf(
                                        'Horas insuficientes: %.1fh trabajadas (mín. %.0fh esperado)',
                                        $horas, $umbralBajoH
                                    ),
                                    'horas_trabajadas' => $horas,
                                ]);
                            } else {
                                $completos[] = $fila;
                            }
                            break;
                        default:
                            $incompletas[] = $fila;
                            break;
                    }
                }
            } else {
                // Sin marcación ese día — verificar si es día de descanso según turno
                $esDescanso = isset($jornadaPorPersona[$pid]) && $jornadaPorPersona[$pid] === false;

                if ($esDescanso) {
                    $descanso[] = $this->buildFilaTrabajador($contrato, [], 'descanso');
                } else {
                    $fila         = $this->buildFilaTrabajador($contrato, [], 'sin_marcacion');
                    $esSinEnrolar = $this->esProbableNuevoSinEnrolar($contrato, $fecha, $diasNuevo);

                    if ($esSinEnrolar) {
                        $probablesNuevos[] = array_merge($fila, ['motivo' => 'Contrato reciente sin marcas previas']);
                    } else {
                        $sinMarcacion[] = $fila;
                    }
                }
            }
        }

        // Ordenar por nombre
        $sortNombre = fn($a, $b) => strcmp($a['nombre'], $b['nombre']);
        usort($completos, $sortNombre);
        usort($incompletas, $sortNombre);
        usort($sinMarcacion, $sortNombre);
        usort($probablesNuevos, $sortNombre);
        usort($descanso, $sortNombre);
        usort($revision, $sortNombre);

        return [
            'fecha'                => $fecha,
            'total_activos'        => count($contratosActivos),
            'total_completos'      => count($completos),
            'total_incompletas'    => count($incompletas),
            'total_sin_marcacion'  => count($sinMarcacion),
            'total_sin_enrolar'    => count($probablesNuevos),
            'total_descanso'       => count($descanso),
            'total_revision'       => count($revision),
            'completos'            => $completos,
            'incompletas'          => $incompletas,
            'sin_marcacion'        => $sinMarcacion,
            'sin_enrolar'          => $probablesNuevos,
            'descanso'             => $descanso,
            'revision'             => $revision,
        ];
    }

    /**
     * Resuelve las horas trabajadas para una persona en el día.
     * Prioridad:
     *  1. working_seconds desde talana_assignation_summaries (dato oficial de Talana)
     *  2. Diferencia entre primera_entrada y ultima_salida (desde las marcas)
     * Devuelve null si no se puede calcular.
     */
    private function resolverHorasTrabajadas(int $pid, array $horasAsignacion, array $fila): ?float
    {
        // Prioridad 1: dato oficial calculado por Talana
        if (isset($horasAsignacion[$pid]) && $horasAsignacion[$pid] > 0) {
            return round($horasAsignacion[$pid] / 3600, 2);
        }

        // Prioridad 2: calcular desde primera_entrada / ultima_salida
        if ($fila['primera_entrada'] && $fila['ultima_salida']) {
            try {
                $tsE = Carbon::createFromFormat('H:i:s', $fila['primera_entrada']);
                $tsS = Carbon::createFromFormat('H:i:s', $fila['ultima_salida']);
                if ($tsS->lte($tsE)) {
                    $tsS->addDay(); // turno nocturno que cruza medianoche
                }
                return round($tsS->diffInSeconds($tsE) / 3600, 2);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Un trabajador es "probable nuevo sin enrolar" si:
     * - Su contrato inició hace ≤ $diasNuevo días, Y
     * - No tiene ninguna marca en la tabla local en los últimos 7 días
     */
    private function esProbableNuevoSinEnrolar($contrato, string $fecha, int $diasNuevo): bool
    {
        if (! $contrato->desde) {
            return false;
        }

        $inicio         = Carbon::parse($contrato->desde);
        $fechaAnalisis  = Carbon::parse($fecha);
        $diasDesdeInicio = $inicio->diffInDays($fechaAnalisis);

        if ($diasDesdeInicio > $diasNuevo) {
            return false; // No es nuevo
        }

        // Verificar en DB local: ¿tiene alguna marca en los últimos 7 días?
        $desde7 = $fechaAnalisis->copy()->subDays(7)->toDateString();

        $tieneMarcasRecientes = TalanaMarca::where('persona_talana_id', $contrato->persona_talana_id)
            ->whereBetween('fecha', [$desde7, $fecha])
            ->exists();

        return ! $tieneMarcasRecientes;
    }

    private function buildFilaTrabajador($contrato, array $marcas, string $categoria): array
    {
        $primeraEntrada  = null;
        $ultimaSalida    = null;
        $marcasStr       = [];

        foreach ($marcas as $m) {
            $etiqueta     = $m['direction'] === 'E' ? 'Entrada' : ($m['direction'] === 'S' ? 'Salida' : $m['direction']);
            $marcasStr[]  = "{$m['ts_str']} ({$etiqueta})";
            if ($m['direction'] === 'E' && ! $primeraEntrada) {
                $primeraEntrada = $m['ts_str'];
            }
            if ($m['direction'] === 'S') {
                $ultimaSalida = $m['ts_str'];
            }
        }

        return [
            'nombre'           => $contrato->persona_nombre ?? '—',
            'rut'              => $contrato->persona_rut    ?? '—',
            'centro_costo'     => $contrato->centro_costo_nombre ?? $contrato->sucursal_nombre ?? '—',
            'cargo'            => $contrato->cargo_nombre   ?? '—',
            'tipo_contrato'    => $contrato->tipo_contrato_nombre ?? '—',
            'empresa_id'       => $contrato->empresa_id     ?? null,
            'empresa'          => $contrato->empresa_nombre ?? 'Sin empresa',
            'desde'            => $contrato->desde          ?? null,
            'hasta'            => $contrato->hasta          ?? null,
            'primera_entrada'  => $primeraEntrada,
            'ultima_salida'    => $ultimaSalida,
            'marcas'           => implode(' / ', $marcasStr),
            'total_marcas'     => count($marcas),
            'categoria'        => $categoria,
            'horas_trabajadas' => null, // se rellena opcionalmente vía resolverHorasTrabajadas
            'motivo'           => null, // se rellena opcionalmente para revisión
        ];
    }

    // ─── Consola ──────────────────────────────────────────────────────────────

    private function mostrarResumen(array $r, string $fecha): void
    {
        $this->line('');
        $this->info("┌─────────────────────────────────────────┐");
        $this->info("│   Resumen Asistencia — {$fecha}   │");
        $this->info("└─────────────────────────────────────────┘");
        $this->line("  Trabajadores activos:          {$r['total_activos']}");
        $this->line("  ✅ Con marcación completa:     {$r['total_completos']}");
        $this->line("  ⚠️  Marcación incompleta (1m):  {$r['total_incompletas']}");
        $this->line("  ❌ Sin marcación:               {$r['total_sin_marcacion']}");
        $this->line("  🆕 Probables nuevos/sin enrolar:{$r['total_sin_enrolar']}");
        $this->line("  😴 Día de descanso (turno):    {$r['total_descanso']}");
        $this->line("  🔍 Revisión (anomalías):        {$r['total_revision']}");

        // Breakdown por empresa
        $empBreakdown = [];
        $grupos = ['completos', 'incompletas', 'sin_marcacion', 'sin_enrolar', 'descanso', 'revision'];
        foreach ($grupos as $g) {
            foreach ($r[$g] as $t) {
                $emp = $t['empresa'] ?? 'Sin empresa';
                if (! isset($empBreakdown[$emp])) {
                    $empBreakdown[$emp] = ['completos' => 0, 'incompletas' => 0, 'sin_marcacion' => 0, 'sin_enrolar' => 0, 'descanso' => 0, 'revision' => 0];
                }
                $empBreakdown[$emp][$g]++;
            }
        }
        if (count($empBreakdown) > 0) {
            $this->line('');
            $this->line('  ─── Por empresa ───────────────────────────');
            foreach ($empBreakdown as $emp => $c) {
                $total = array_sum($c);
                $this->line("  {$emp} ({$total}): ✅ {$c['completos']} | ⚠️ {$c['incompletas']} | ❌ {$c['sin_marcacion']} | 😴 {$c['descanso']} | 🔍 {$c['revision']}");
            }
        }

        if (! empty($r['revision'])) {
            $this->line('');
            $this->warn('  Revisión (requieren atención):');
            foreach ($r['revision'] as $t) {
                $motivo = $t['motivo'] ?? 'Sin motivo';
                $this->line("    - {$t['nombre']} ({$t['rut']}) | {$motivo} | Marcas: {$t['marcas']}");
            }
        }

        if (! empty($r['incompletas'])) {
            $this->line('');
            $this->warn('  Marcación incompleta:');
            foreach ($r['incompletas'] as $t) {
                $this->line("    - {$t['nombre']} ({$t['rut']}) | {$t['marcas']} | {$t['categoria']}");
            }
        }

        if (! empty($r['sin_enrolar'])) {
            $this->line('');
            $this->warn('  Probables nuevos sin enrolar:');
            foreach ($r['sin_enrolar'] as $t) {
                $this->line("    - {$t['nombre']} ({$t['rut']}) | contrato desde: {$t['desde']}");
            }
        }
    }

    private function cnt(array $arr): int
    {
        return count($arr);
    }
}
