<?php

namespace App\Http\Controllers;

use App\Jobs\TalanaSyncJob;
use App\Models\TalanaAusencia;
use App\Models\TalanaContrato;
use App\Models\TalanaMarca;
use App\Models\TalanaPersona;
use App\Models\TalanaSaldoVacaciones;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GrafanaController extends Controller
{
    /**
     * Página principal de Grafana embebida (solo SUPER_ADMIN).
     */
    public function index(Request $request)
    {
        $grafanaUrl = rtrim((string) config('services.grafana.url', ''), '/');
        $dashUid    = config('services.grafana.dashboard_uid', 'talana-saep');

        $embedUrl = "{$grafanaUrl}/d/{$dashUid}/talana-saep?orgId=1&kiosk=tv&refresh=5m";

        $stats    = $this->getStats();
        $syncInfo = $this->buildSyncInfo();

        return view('grafana.index', compact('embedUrl', 'stats', 'syncInfo', 'grafanaUrl', 'dashUid'));
    }

    /**
     * Estadísticas rápidas en JSON (AJAX).
     */
    public function stats()
    {
        return response()->json([
            'stats'    => $this->getStats(),
            'syncInfo' => $this->buildSyncInfo(),
        ]);
    }

    /**
     * Lanza sync en background (sin cola) y devuelve estado JSON.
     * POST /grafana/sync
     */
    public function sync(Request $request)
    {
        // Evitar disparos simultáneos
        if (Cache::get('talana_sync_running', false)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ya hay un sync en curso, espera a que termine.',
                'running' => true,
            ], 409);
        }

        // Throttle: no más de 1 sync manual cada 3 minutos
        $throttleKey = 'talana_sync_manual_throttle';
        if (Cache::has($throttleKey)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Espera 3 minutos antes de lanzar otro sync manual.',
                'running' => false,
            ], 429);
        }

        Cache::put($throttleKey, true, now()->addMinutes(3));
        Cache::put('talana_sync_running', true, now()->addMinutes(15));
        Cache::put('talana_sync_started_at', now()->toDateTimeString(), now()->addHours(2));
        Cache::forget('talana_sync_error');
        Cache::forget('talana_sync_finished_at');

        // Lanzar artisan en background (no necesita queue worker)
        $artisan  = PHP_BINARY . ' ' . escapeshellarg(base_path('artisan'));
        $appEnv   = app()->environment();
        $logFile  = storage_path('logs/talana-sync-manual.log');
        $wrapperScript = base_path('grafana/run_sync.sh');

        // Script wrapper que actualiza el cache al terminar
        $cmd = "nohup {$artisan} talana:sync-db --meses=1 >> " . escapeshellarg($logFile) . " 2>&1 &";

        // Ejecutar de forma no bloqueante
        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open($cmd, $descriptors, $pipes, base_path());
        if (is_resource($proc)) {
            foreach ($pipes as $pipe) { fclose($pipe); }
            proc_close($proc);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Sync iniciado en segundo plano. Los datos se actualizarán en 1-3 minutos.',
            'running' => true,
        ]);
    }

    /**
     * Estado actual del sync (AJAX polling).
     * GET /grafana/sync-status
     */
    public function syncStatus()
    {
        return response()->json($this->buildSyncInfo());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildSyncInfo(): array
    {
        $lastSyncContratos = TalanaContrato::max('synced_at');
        $lastSyncMarcas    = TalanaMarca::max('synced_at');
        $running           = (bool) Cache::get('talana_sync_running', false);
        $startedAt         = Cache::get('talana_sync_started_at');
        $finishedAt        = Cache::get('talana_sync_finished_at');
        $error             = Cache::get('talana_sync_error');

        // Próximo sync programado: 06:00 AM hora servidor
        $next = Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        if ($next->isPast()) {
            $next->addDay();
        }

        // ¿Datos frescos? (contratos sincronizados en las últimas 25 h)
        $stale = ! $lastSyncContratos || Carbon::parse($lastSyncContratos)->diffInHours(now()) > 25;

        return [
            'running'            => $running,
            'stale'              => $stale,
            'error'              => $error,
            'last_contratos'     => $lastSyncContratos,
            'last_marcas'        => $lastSyncMarcas,
            'started_at'         => $startedAt,
            'finished_at'        => $finishedAt,
            'next_scheduled'     => $next->toDateTimeString(),
            'next_scheduled_human' => $next->diffForHumans(),
            'total_personas'     => TalanaPersona::count(),
            'total_contratos'    => TalanaContrato::count(),
            'total_marcas'       => TalanaMarca::count(),
            'last_contratos_human' => $lastSyncContratos
                ? Carbon::parse($lastSyncContratos)->diffForHumans()
                : null,
            'last_marcas_human'  => $lastSyncMarcas
                ? Carbon::parse($lastSyncMarcas)->diffForHumans()
                : null,
            // ── RRHH ──────────────────────────────────────────────────────────
            'rrhh_running'           => (bool) Cache::get('talana_rrhh_sync_running', false),
            'rrhh_error'             => Cache::get('talana_rrhh_sync_error'),
            'rrhh_finished_at'       => Cache::get('talana_rrhh_sync_finished_at'),
            'total_ausencias'        => TalanaAusencia::count(),
            'total_saldo_vacaciones' => TalanaSaldoVacaciones::count(),
        ];
    }

    /**
     * Datos para el dashboard nativo de Chart.js.
     * GET /grafana/charts?centro_costo=...&tipo_contrato=...
     */
    public function charts(Request $request): \Illuminate\Http\JsonResponse
    {
        $centroCosto  = $request->input('centro_costo', '');
        $tipoContrato = $request->input('tipo_contrato', '');
        $hoy          = now()->toDateString();

        // Closure que devuelve un builder base de contratos vigentes con filtros aplicados
        $vigentesBase = function () use ($hoy, $centroCosto, $tipoContrato) {
            $q = TalanaContrato::where('finiquitado', false)
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy);
                });
            if ($centroCosto)  $q->where('centro_costo_nombre', $centroCosto);
            if ($tipoContrato) $q->where('tipo_contrato_nombre', $tipoContrato);
            return $q;
        };

        // 1. Contratos por tipo (donut)
        $contratosPorTipo = $vigentesBase()
            ->select('tipo_contrato_nombre as label', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tipo_contrato_nombre')
            ->groupBy('tipo_contrato_nombre')
            ->orderByDesc('total')
            ->get();

        // 2. Top centros de costo (barras horizontales)
        $porCentro = $vigentesBase()
            ->select('centro_costo_nombre as label', DB::raw('COUNT(*) as total'))
            ->whereNotNull('centro_costo_nombre')
            ->groupBy('centro_costo_nombre')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        // 3. Vencimientos próximos 12 meses (barras verticales)
        $en12     = now()->addMonths(12)->toDateString();
        $vencBase = TalanaContrato::where('finiquitado', false)
            ->whereNotNull('hasta')
            ->whereBetween('hasta', [$hoy, $en12]);
        if ($centroCosto)  $vencBase->where('centro_costo_nombre', $centroCosto);
        if ($tipoContrato) $vencBase->where('tipo_contrato_nombre', $tipoContrato);
        $vencimientosPorMes = $vencBase
            ->select(DB::raw("DATE_FORMAT(hasta,'%Y-%m') as label"), DB::raw('COUNT(*) as total'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        // 4. Marcas por día (últimos 30 días) — línea
        $ini30     = now()->subDays(29)->toDateString();
        $marcasBase = TalanaMarca::whereBetween('fecha', [$ini30, $hoy]);
        if ($centroCosto) $marcasBase->where('centro_costo_nombre', $centroCosto);
        $marcasPorDia = $marcasBase
            ->select('fecha as label', DB::raw('COUNT(*) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // 5. Top cargos (barras horizontales)
        $cargos = $vigentesBase()
            ->select('cargo_nombre as label', DB::raw('COUNT(*) as total'))
            ->whereNotNull('cargo_nombre')
            ->groupBy('cargo_nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 6. Tabla: próximos a vencer en 60 días
        $en60         = now()->addDays(60)->toDateString();
        $proximosBase = TalanaContrato::where('finiquitado', false)
            ->whereNotNull('hasta')
            ->whereBetween('hasta', [$hoy, $en60]);
        if ($centroCosto)  $proximosBase->where('centro_costo_nombre', $centroCosto);
        if ($tipoContrato) $proximosBase->where('tipo_contrato_nombre', $tipoContrato);
        $proximosVencer = $proximosBase
            ->orderBy('hasta')
            ->limit(60)
            ->get(['persona_nombre', 'persona_rut', 'cargo_nombre', 'centro_costo_nombre', 'hasta', 'tipo_contrato_nombre']);

        // Opciones de filtro (siempre del dataset completo vigente sin filtros)
        $filtrosCentros = TalanaContrato::where('finiquitado', false)
            ->whereNotNull('centro_costo_nombre')
            ->where(function ($q) use ($hoy) {
                $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy);
            })
            ->distinct()
            ->orderBy('centro_costo_nombre')
            ->pluck('centro_costo_nombre');

        $filtrosTipos = TalanaContrato::where('finiquitado', false)
            ->whereNotNull('tipo_contrato_nombre')
            ->where(function ($q) use ($hoy) {
                $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy);
            })
            ->distinct()
            ->orderBy('tipo_contrato_nombre')
            ->pluck('tipo_contrato_nombre');

        // 7. Asistencia diaria — personas únicas (últimos 30 días)
        $asistenciaDiaria = TalanaMarca::whereBetween('fecha', [$ini30, $hoy])
            ->select('fecha as label', DB::raw('COUNT(DISTINCT persona_talana_id) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // 8. Vencimientos por centro de costo — próximos 90 días
        $en90 = now()->addDays(90)->toDateString();
        $vencPorCentroQ = TalanaContrato::where('finiquitado', false)
            ->whereNotNull('hasta')
            ->whereBetween('hasta', [$hoy, $en90])
            ->whereNotNull('centro_costo_nombre');
        if ($centroCosto)  $vencPorCentroQ->where('centro_costo_nombre', $centroCosto);
        if ($tipoContrato) $vencPorCentroQ->where('tipo_contrato_nombre', $tipoContrato);
        $vencPorCentro = $vencPorCentroQ
            ->select('centro_costo_nombre as label', DB::raw('COUNT(*) as total'))
            ->groupBy('centro_costo_nombre')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        // 9. Calendario de vencimientos — próximos 6 meses, por día
        $en6m = now()->addMonths(6)->toDateString();
        $calendarioQ = TalanaContrato::where('finiquitado', false)
            ->whereNotNull('hasta')
            ->whereBetween('hasta', [$hoy, $en6m]);
        if ($centroCosto)  $calendarioQ->where('centro_costo_nombre', $centroCosto);
        if ($tipoContrato) $calendarioQ->where('tipo_contrato_nombre', $tipoContrato);
        $calendarioVenc = $calendarioQ
            ->select(DB::raw("DATE_FORMAT(hasta,'%Y-%m-%d') as fecha"), DB::raw('COUNT(*) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'filters' => [
                'centros_costo'  => $filtrosCentros,
                'tipos_contrato' => $filtrosTipos,
            ],
            'contratos_por_tipo'      => $contratosPorTipo,
            'por_centro_costo'        => $porCentro,
            'vencimientos_por_mes'    => $vencimientosPorMes,
            'marcas_por_dia'          => $marcasPorDia,
            'cargos_top'              => $cargos,
            'proximos_vencer'         => $proximosVencer,
            'asistencia_diaria'       => $asistenciaDiaria,
            'vencimientos_por_centro' => $vencPorCentro,
            'calendario_vencimientos' => $calendarioVenc,
            // ── RRHH ──────────────────────────────────────────────────────────
            'ausencias_por_tipo'      => $this->ausenciasPorTipo(),
            'ausencias_por_mes'       => $this->ausenciasPorMes(),
            'distribucion_vacaciones' => $this->distribucionVacaciones(),
            // ── ANALYTICS CRUZADOS ────────────────────────────────────────────
            'ausencias_por_centro'    => $this->ausenciasPorCentro(),
            'vacaciones_por_centro'   => $this->vacacionesPorCentro(),
            'top_ausentes'            => $this->topAusentes(),
            'marcas_dia_semana'       => $this->marcasPorDiaSemana(),
            'correlacion_mensual'     => $this->correlacionMensual(),
            'headcount_centro_tipo'   => $this->headcountCentroTipo(),
        ]);
    }

    private function ausenciasPorTipo(): \Illuminate\Support\Collection
    {
        $desde12m = now()->subMonths(12)->startOfMonth()->toDateString();
        return TalanaAusencia::where('fecha_desde', '>=', $desde12m)
            ->where('aprobada', true)
            ->select('tipo_ausencia as label', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tipo_ausencia')
            ->groupBy('tipo_ausencia')
            ->orderByDesc('total')
            ->get();
    }

    private function ausenciasPorMes(): \Illuminate\Support\Collection
    {
        $desde12m = now()->subMonths(11)->startOfMonth()->toDateString();
        return TalanaAusencia::where('fecha_desde', '>=', $desde12m)
            ->where('aprobada', true)
            ->select(DB::raw("DATE_FORMAT(fecha_desde,'%Y-%m') as label"), DB::raw('COUNT(*) as total'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
    }

    private function distribucionVacaciones(): array
    {
        $buckets = [
            'Sin días'  => TalanaSaldoVacaciones::where('dias_restantes', '<=', 0)->count(),
            '1–5 días'  => TalanaSaldoVacaciones::whereBetween('dias_restantes', [0.001, 5])->count(),
            '6–10 días' => TalanaSaldoVacaciones::whereBetween('dias_restantes', [5.001, 10])->count(),
            '11–15 días'=> TalanaSaldoVacaciones::whereBetween('dias_restantes', [10.001, 15])->count(),
            '16+ días'  => TalanaSaldoVacaciones::where('dias_restantes', '>', 15)->count(),
        ];

        return collect($buckets)->map(fn($total, $label) => ['label' => $label, 'total' => $total])->values()->all();
    }

    // ── ANALYTICS CRUZADOS ────────────────────────────────────────────────────

    /** Ausentismo por centro de costo (JOIN ausencias → contratos) últimos 12m */
    private function ausenciasPorCentro(): \Illuminate\Support\Collection
    {
        $desde = now()->subMonths(12)->startOfMonth()->toDateString();
        return DB::table('talana_ausencias as a')
            ->join('talana_contratos as c', 'a.empleado_id', '=', 'c.persona_talana_id')
            ->where('a.fecha_desde', '>=', $desde)
            ->where('a.aprobada', true)
            ->whereNotNull('c.centro_costo_nombre')
            ->where('c.finiquitado', false)
            ->select('c.centro_costo_nombre as label',
                     DB::raw('COUNT(a.id) as total'),
                     DB::raw('SUM(a.numero_dias) as dias_total'))
            ->groupBy('c.centro_costo_nombre')
            ->orderByDesc('dias_total')
            ->limit(12)
            ->get();
    }

    /** Vacaciones pendientes por centro de costo (JOIN saldo_vacaciones → contratos) */
    private function vacacionesPorCentro(): \Illuminate\Support\Collection
    {
        return DB::table('talana_saldo_vacaciones as v')
            ->join('talana_contratos as c', 'v.empleado_id', '=', 'c.persona_talana_id')
            ->where('c.finiquitado', false)
            ->whereNotNull('c.centro_costo_nombre')
            ->where('v.dias_restantes', '>', 0)
            ->select('c.centro_costo_nombre as label',
                     DB::raw('COUNT(v.id) as personas'),
                     DB::raw('ROUND(SUM(v.dias_restantes), 1) as dias_total'))
            ->groupBy('c.centro_costo_nombre')
            ->orderByDesc('dias_total')
            ->limit(12)
            ->get();
    }

    /** Top 10 personas con más días de ausencia (últimos 12 meses) */
    private function topAusentes(): \Illuminate\Support\Collection
    {
        $desde = now()->subMonths(12)->startOfMonth()->toDateString();
        return TalanaAusencia::where('fecha_desde', '>=', $desde)
            ->where('aprobada', true)
            ->select('persona_nombre as label',
                     DB::raw('COUNT(*) as eventos'),
                     DB::raw('SUM(numero_dias) as dias_total'))
            ->whereNotNull('persona_nombre')
            ->groupBy('persona_nombre')
            ->orderByDesc('dias_total')
            ->limit(10)
            ->get();
    }

    /** Marcas por día de la semana (últimos 90 días) — patrón de asistencia */
    private function marcasPorDiaSemana(): \Illuminate\Support\Collection
    {
        $desde = now()->subDays(89)->toDateString();
        $labels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $rows = DB::table('talana_marcas')
            ->where('fecha', '>=', $desde)
            ->where('tipo', 'E')
            ->select(DB::raw('DAYOFWEEK(fecha) as dow'), DB::raw('COUNT(DISTINCT persona_talana_id) as total'))
            ->groupBy('dow')
            ->orderBy('dow')
            ->get()
            ->keyBy('dow');

        return collect(range(1, 7))->map(fn($d) => [
            'label' => $labels[$d - 1],
            'total' => $rows->get($d)?->total ?? 0,
        ]);
    }

    /** Correlación mensual: asistencia única vs ausencias (últimos 12 meses) */
    private function correlacionMensual(): array
    {
        $desde = now()->subMonths(11)->startOfMonth()->toDateString();

        $asistencia = DB::table('talana_marcas')
            ->where('fecha', '>=', $desde)
            ->where('tipo', 'E')
            ->select(DB::raw("DATE_FORMAT(fecha,'%Y-%m') as mes"), DB::raw('COUNT(DISTINCT persona_talana_id) as total'))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        $ausencias = DB::table('talana_ausencias')
            ->where('fecha_desde', '>=', $desde)
            ->where('aprobada', true)
            ->select(DB::raw("DATE_FORMAT(fecha_desde,'%Y-%m') as mes"), DB::raw('COUNT(*) as total'))
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        $meses = collect();
        $cursor = now()->subMonths(11)->startOfMonth()->copy();
        $fin    = now()->startOfMonth();
        while ($cursor->lte($fin)) {
            $key = $cursor->format('Y-m');
            $meses->push([
                'label'      => $cursor->translatedFormat('M y'),
                'asistencia' => $asistencia->get($key)?->total ?? 0,
                'ausencias'  => $ausencias->get($key)?->total ?? 0,
            ]);
            $cursor->addMonth();
        }
        return $meses->all();
    }

    /** Headcount por tipo de contrato vs centro de costo — stacked bar */
    private function headcountCentroTipo(): \Illuminate\Support\Collection
    {
        $hoy = now()->toDateString();
        return DB::table('talana_contratos')
            ->where('finiquitado', false)
            ->where(fn($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
            ->whereNotNull('centro_costo_nombre')
            ->whereNotNull('tipo_contrato_nombre')
            ->select('centro_costo_nombre as centro',
                     'tipo_contrato_nombre as tipo',
                     DB::raw('COUNT(*) as total'))
            ->groupBy('centro_costo_nombre', 'tipo_contrato_nombre')
            ->orderByDesc('total')
            ->get();
    }

    private function getStats(): array
    {
        $hoy    = now()->toDateString();
        $en30   = now()->addDays(30)->toDateString();
        $en7    = now()->addDays(7)->toDateString();
        $mesIni = now()->startOfMonth()->toDateString();

        return [
            'total_trabajadores' => TalanaPersona::where('activo', true)->count(),

            'contratos_vigentes' => TalanaContrato::where('finiquitado', false)
                ->where(fn($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
                ->count(),

            'contratos_indefinidos' => TalanaContrato::where('finiquitado', false)
                ->whereNull('hasta')
                ->count(),

            'contratos_plazo_fijo' => TalanaContrato::where('finiquitado', false)
                ->whereNotNull('hasta')
                ->where('hasta', '>=', $hoy)
                ->count(),

            'proximos_vencer_30' => TalanaContrato::where('finiquitado', false)
                ->whereBetween('hasta', [$hoy, $en30])
                ->count(),

            'proximos_vencer_7' => TalanaContrato::where('finiquitado', false)
                ->whereBetween('hasta', [$hoy, $en7])
                ->count(),

            'vencidos_activos' => TalanaContrato::where('finiquitado', false)
                ->whereNotNull('hasta')
                ->where('hasta', '<', $hoy)
                ->count(),

            'marcas_mes_actual' => TalanaMarca::where('fecha', '>=', $mesIni)->count(),

            'entradas_hoy' => TalanaMarca::where('fecha', $hoy)->where('tipo', 'E')->count(),

            'activos_con_marca_30d' => TalanaMarca::where('fecha', '>=', now()->subDays(29)->toDateString())
                ->distinct('persona_talana_id')
                ->count('persona_talana_id'),

            'proximos_vencer_90' => TalanaContrato::where('finiquitado', false)
                ->whereBetween('hasta', [$hoy, now()->addDays(90)->toDateString()])
                ->count(),

            // ── RRHH: Vacaciones ──────────────────────────────────────────────
            'total_vacaciones_dias' => round((float) TalanaSaldoVacaciones::sum('dias_restantes'), 1),

            'personas_sin_vacaciones' => TalanaSaldoVacaciones::where('dias_restantes', '<=', 0)->count(),

            // ── RRHH: Ausencias ───────────────────────────────────────────────
            'ausencias_mes_actual' => TalanaAusencia::whereYear('fecha_desde', now()->year)
                ->whereMonth('fecha_desde', now()->month)
                ->where('aprobada', true)
                ->count(),

            'licencias_medicas_activas' => TalanaAusencia::where('tipo_ausencia', 'licencia medica')
                ->where('aprobada', true)
                ->where('fecha_hasta', '>=', $hoy)
                ->count(),

            'faltas_injustificadas_30d' => TalanaAusencia::where('tipo_ausencia', 'falta injustificada')
                ->where('fecha_desde', '>=', now()->subDays(30)->toDateString())
                ->count(),
        ];
    }
}
