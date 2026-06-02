<?php

namespace App\Console\Commands;

use App\Mail\TalanaAsistenciaReporteMail;
use App\Models\TalanaAssignationSummary;
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
                            {--fecha=        : Fecha YYYY-MM-DD a analizar (default: ayer)}
                            {--email=        : Email destinatario (default: TALANA_ALERTA_EMAIL)}
                            {--dias-nuevo=60 : Días de antigüedad máxima para marcar como "nuevo"}
                            {--dry-run       : Muestra resumen por consola sin enviar email}';

    protected $description = 'Genera el reporte diario de asistencia Talana y lo envía por email';

    public function __construct(private readonly TalanaService $talana)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha    = $this->option('fecha') ?: Carbon::yesterday()->toDateString();
        $isDry    = $this->option('dry-run');
        $diasNuevo = (int) ($this->option('dias-nuevo') ?? 60);
        $email    = $this->option('email') ?: config('services.talana.alerta_email');

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

        // ─── 4. Cargar mapa de jornada calculada (workingDay) desde DB local ──────
        // Permite distinguir días de descanso (workingDay=false) de ausencias reales.
        // Si la tabla aún no ha sido sincronizada, $jornadaPorPersona estará vacío
        // y el reporte recae en el comportamiento anterior (sin filtro por descanso).
        $jornadaPorPersona = [];
        try {
            $jornadaPorPersona = TalanaAssignationSummary::mapaJornadaPorFecha($fecha);
            if (empty($jornadaPorPersona)) {
                $this->warn('   ⚠ Sin datos de jornada en DB para ' . $fecha . '. Ejecuta talana:sync-turnos para activar el filtro de días de descanso.');
            } else {
                $this->line('   ✓ Jornada calculada: ' . count($jornadaPorPersona) . ' registros cargados desde DB');
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
            $diasNuevo
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
        int $diasNuevo
    ): array {
        $completos        = [];
        $incompletas      = [];
        $sinMarcacion     = [];
        $probablesNuevos  = [];
        $descanso         = []; // Día de descanso según turno (workingDay = false)
        $revision         = []; // Marcó en día de descanso — requiere revisión

        // IDs de personas con contrato activo que ya marcaron (o aparecen en marcas)
        $personasConMarca = array_keys($marcasPorPersona);

        // Personas que marcaron ese día agrupadas por persona_talana_id
        // (los contratos usan persona_talana_id = person.id en Talana)
        foreach ($contratosActivos as $contrato) {
            $pid = $contrato->persona_talana_id;

            if (isset($marcasPorPersona[$pid])) {
                $data = $marcasPorPersona[$pid];
                $fila = $this->buildFilaTrabajador($contrato, $data['marcas'], $data['categoria']);

                // Si marcó pero su jornada es día de descanso → requiere revisión
                $esDescansoConMarca = isset($jornadaPorPersona[$pid]) && $jornadaPorPersona[$pid] === false;

                if ($esDescansoConMarca) {
                    $revision[] = array_merge($fila, ['categoria' => 'revision']);
                } else {
                    switch ($data['categoria']) {
                        case 'completo':
                        case 'multiple':
                            $completos[] = $fila;
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
                    // Día de descanso según turno rotativo — no es anomalía
                    $descanso[] = $this->buildFilaTrabajador($contrato, [], 'descanso');
                } else {
                    // Día laborable (o sin datos de jornada) y sin marcación
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
        $this->line("  🔍 Marcó en día de descanso:    {$r['total_revision']}");

        if (! empty($r['revision'])) {
            $this->line('');
            $this->warn('  Marcó en día de descanso (revisar):');
            foreach ($r['revision'] as $t) {
                $this->line("    - {$t['nombre']} ({$t['rut']}) | {$t['marcas']}");
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
