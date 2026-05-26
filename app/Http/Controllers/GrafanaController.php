<?php

namespace App\Http\Controllers;

use App\Jobs\TalanaSyncJob;
use App\Models\TalanaContrato;
use App\Models\TalanaMarca;
use App\Models\TalanaPersona;
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

        return response()->json([
            'filters' => [
                'centros_costo'  => $filtrosCentros,
                'tipos_contrato' => $filtrosTipos,
            ],
            'contratos_por_tipo'   => $contratosPorTipo,
            'por_centro_costo'     => $porCentro,
            'vencimientos_por_mes' => $vencimientosPorMes,
            'marcas_por_dia'       => $marcasPorDia,
            'cargos_top'           => $cargos,
            'proximos_vencer'      => $proximosVencer,
        ]);
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
        ];
    }
}
