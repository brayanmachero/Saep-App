<?php

namespace App\Http\Controllers;

use App\Console\Commands\KizeoCharlaWeeklyReport;
use App\Jobs\DashboardSyncJob;
use App\Mail\CharlaTrackingReporteMail;
use App\Models\CharlaTrackingActionLog;
use App\Models\KizeoCharlaTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CharlaTrackingController extends Controller
{
    public function index(Request $request)
    {
        $filters = KizeoCharlaWeeklyReport::normalizeReportFilters([
            'desde' => $request->input('desde', now()->subDays(30)->format('Y-m-d')),
            'hasta' => $request->input('hasta', now()->format('Y-m-d')),
            'tipo_fecha' => $request->input('tipo_fecha', 'respuesta'),
            'estado' => $request->input('estado', 'todos'),
            'buscar' => $request->input('buscar'),
        ]);

        $desde = $filters['desde'];
        $hasta = $filters['hasta'];
        $estado = $filters['estado'] ?? 'todos';
        $buscar = $filters['buscar'] ?? null;
        $tipoFecha = $filters['tipo_fecha'];
        $esHistorico = $tipoFecha === 'respuesta';

        $charlaActionLogs = $this->recentActionLogs();

        // === KPIs ===
        $baseQuery = $this->trackingQuery($filters);
        $total        = (clone $baseQuery)->count();
        $completadas  = (clone $baseQuery)->completados()->count();
        $transferidos = (clone $baseQuery)->transferidos()->count();
        $pendientes   = (clone $baseQuery)->pendientes()->count();
        $tasa         = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        // Promedio días pendientes
        $promDias = (clone $baseQuery)
            ->pendientes()
            ->selectRaw('AVG(DATEDIFF(NOW(), COALESCE(fecha_asignacion, fecha_creacion))) as prom')
            ->value('prom');
        $promDias = $promDias ? round($promDias, 1) : 0;

        // Los dos totales tienen bases distintas y se muestran explícitamente.
        $filterCommon = $filters;
        $filterCommon['estado'] = 'todos';
        $respondidasPeriodo = $this->trackingQuery(array_merge($filterCommon, ['tipo_fecha' => 'respuesta']))->count();
        $creadasPeriodo = $this->trackingQuery(array_merge($filterCommon, ['tipo_fecha' => 'creacion']))->count();

        // === DATOS PARA GRÁFICOS ===

        // 1. Tendencia semanal
        $tendencia = $esHistorico ? (clone $baseQuery)
            ->whereNotNull('fecha_respuesta')
            ->get(['fecha_respuesta'])
            ->groupBy(fn ($row) => $row->fecha_respuesta->format('o-W'))
            ->sortKeys()
            ->map(function ($rows) {
                $date = $rows->first()->fecha_respuesta->copy()->startOfWeek();
                return [
                    'label' => 'S' . $date->isoWeek() . ' (' . $date->format('d/m') . ')',
                    'total' => $rows->count(),
                    'completadas' => $rows->count(),
                    'pendientes' => 0,
                    'tasa' => 100,
                ];
            })->values()
            : (clone $baseQuery)
            ->selectRaw("anio, semana,
                         COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('anio', 'semana')
            ->orderBy('anio')
            ->orderBy('semana')
            ->get()
            ->map(function ($row) {
                $date = Carbon::now()->setISODate($row->anio, $row->semana)->startOfWeek();
                return [
                    'label'       => 'S' . $row->semana . ' (' . $date->format('d/m') . ')',
                    'total'       => $row->total,
                    'completadas' => $row->completadas,
                    'pendientes'  => $row->pendientes,
                    'tasa'        => $row->total > 0 ? round(($row->completadas / $row->total) * 100, 1) : 0,
                ];
            });

        // 2. Distribución por estatus Kizeo (doughnut)
        $distribucion = (clone $baseQuery)
            ->selectRaw("estatus_kizeo, COUNT(*) as cantidad")
            ->groupBy('estatus_kizeo')
            ->pluck('cantidad', 'estatus_kizeo')
            ->toArray();

        // 3. Top asignadores (quién crea/asigna más charlas — incluye directas y transferidas)
        $topAsignadores = (clone $baseQuery)
            ->selectRaw("asignado_por as usuario,
                         COUNT(*) as total_asignadas,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('asignado_por')
            ->orderByDesc('total_asignadas')
            ->limit(10)
            ->get();

        // 4. Cumplimiento por destinatario
        $porDestinatario = (clone $baseQuery)
            ->whereNotNull('asignado_a')
            ->selectRaw("asignado_a as destinatario,
                         COUNT(*) as total_recibidas,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estatus_kizeo='recuperado' THEN 1 ELSE 0 END) as recuperadas,
                         SUM(CASE WHEN estatus_kizeo='transferido' THEN 1 ELSE 0 END) as sin_descargar")
            ->groupBy('asignado_a')
            ->orderByRaw("SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) DESC")
            ->limit(10)
            ->get();

        // 5. Cumplimiento por usuario (creador)
        $porUsuario = (clone $baseQuery)
            ->selectRaw("asignado_por as usuario,
                         COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('asignado_por')
            ->orderByRaw("SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) DESC")
            ->limit(15)
            ->get();

        // 6. Distribución por lugar/CD
        $porLugar = (clone $baseQuery)
            ->whereNotNull('lugar')
            ->where('lugar', '!=', '')
            ->selectRaw("lugar, COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('lugar')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 7. Top pendientes por responsable
        $topPendientes = (clone $baseQuery)
            ->pendientes()
            ->selectRaw("COALESCE(asignado_a, asignado_por) as responsable,
                         COUNT(*) as cantidad,
                         MIN(COALESCE(fecha_asignacion, fecha_creacion)) as mas_antigua,
                         MAX(DATEDIFF(NOW(), COALESCE(fecha_asignacion, fecha_creacion))) as dias_max")
            ->groupBy('responsable')
            ->orderByDesc('dias_max')
            ->limit(10)
            ->get();

        // 8. Tabla detalle filtrable
        $queryDetalle = $this->trackingQuery($filters);

        $registrosList = $queryDetalle
            ->orderByDesc($esHistorico ? 'fecha_respuesta' : 'fecha_creacion')
            ->paginate(20)
            ->withQueryString();

        $ultimaSync = Cache::get('charla_tracking_last_sync');
        $sincronizacionAtrasada = !$ultimaSync || Carbon::parse($ultimaSync)->lt(now()->subHours(12));

        return view('charla-tracking.index', compact(
            'desde', 'hasta', 'estado', 'buscar', 'tipoFecha', 'esHistorico', 'filters',
            'total', 'completadas', 'pendientes', 'transferidos', 'tasa', 'promDias',
            'respondidasPeriodo', 'creadasPeriodo', 'sincronizacionAtrasada',
            'porUsuario', 'tendencia', 'distribucion',
            'topAsignadores', 'porDestinatario', 'porLugar',
            'registrosList', 'topPendientes', 'ultimaSync', 'charlaActionLogs'
        ));
    }

    public function sync(Request $request)
    {
        try {
            $queued = DashboardSyncJob::dispatchOnce(
                'charlas',
                'kizeo:sync-charla-tracking',
                ['--months' => 6],
                $request->user()?->id,
                'charla',
            );
        } catch (\Throwable $e) {
            $this->recordAction($request, 'sync', 'failed', 'No fue posible encolar la sincronización manual de charlas.', [], [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'No fue posible iniciar la sincronización: ' . $e->getMessage());
        }

        if (! $queued) {
            return back()->with('error', 'Ya hay una sincronización de charlas en curso. Espera a que termine antes de iniciar otra.');
        }

        $this->recordAction($request, 'sync', 'queued', 'Sincronización manual de charlas enviada a segundo plano.', [], [
            'queue' => DashboardSyncJob::QUEUE,
        ]);

        return back()->with('success', 'Sincronización iniciada en segundo plano. Puedes continuar usando la plataforma mientras termina.');
    }

    public function emailPreview(Request $request)
    {
        $filters = $this->reportFiltersFromRequest($request);
        $data = KizeoCharlaWeeklyReport::buildReportDataFromFilters($filters);

        $mailable = new CharlaTrackingReporteMail(
            $data['stats'],
            $data['pendientesPorUsuario'],
            $data['resumenSemanal'],
            $data['topDestinatarios'],
            $data['periodo'],
        );

        return $mailable->render();
    }

    public function sendNow(Request $request)
    {
        $filters = $this->reportFiltersFromRequest($request);
        $options = ['--sync' => true];

        foreach ($filters as $key => $value) {
            $options["--{$key}"] = $value;
        }

        try {
            $exitCode = Artisan::call('kizeo:charla-weekly-report', $options);
            $output = Artisan::output();

            $status = $exitCode === 0 ? 'success' : 'failed';
            $this->recordAction($request, 'report_send_now', $status, 'Ejecución manual de reporte de charlas.', $filters, [
                'output' => trim($output),
                'exit_code' => $exitCode,
            ]);

            if ($exitCode !== 0) {
                return back()->with('error', 'El reporte no se pudo enviar. ' . trim($output));
            }

            return back()->with('success', 'Reporte enviado exitosamente. ' . trim($output));
        } catch (\Throwable $e) {
            $this->recordAction($request, 'report_send_now', 'failed', 'Error al ejecutar reporte manual de charlas.', $filters, [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al enviar reporte: ' . $e->getMessage());
        }
    }

    private function reportFiltersFromRequest(Request $request): array
    {
        return KizeoCharlaWeeklyReport::normalizeReportFilters(
            $request->only(KizeoCharlaWeeklyReport::reportFilterKeys())
        );
    }

    private function trackingQuery(array $filters)
    {
        $desde = Carbon::parse($filters['desde'])->startOfDay()->toDateTimeString();
        $hasta = Carbon::parse($filters['hasta'])->endOfDay()->toDateTimeString();

        $dateColumn = ($filters['tipo_fecha'] ?? 'creacion') === 'respuesta'
            ? 'fecha_respuesta'
            : 'fecha_creacion';
        $query = KizeoCharlaTracking::query()->whereBetween($dateColumn, [$desde, $hasta]);

        if (($filters['estado'] ?? 'todos') === 'pendiente') {
            $query->pendientes();
        } elseif (($filters['estado'] ?? 'todos') === 'completado') {
            $query->completados();
        } elseif (($filters['estado'] ?? 'todos') === 'transferido') {
            $query->transferidos();
        }

        if (!empty($filters['buscar'])) {
            $buscar = $filters['buscar'];
            $query->where(function ($q) use ($buscar) {
                $q->where('asignado_por', 'like', "%{$buscar}%")
                    ->orWhere('asignado_a', 'like', "%{$buscar}%")
                    ->orWhere('titulo_actividad', 'like', "%{$buscar}%")
                    ->orWhere('lugar', 'like', "%{$buscar}%");
            });
        }

        return $query;
    }

    private function recordAction(
        Request $request,
        string $action,
        string $status,
        ?string $summary = null,
        array $filters = [],
        array $metadata = []
    ): void {
        $userAgent = $request->userAgent();

        CharlaTrackingActionLog::record([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'status' => $status,
            'summary' => $summary,
            'filters' => $filters ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
        ]);
    }

    private function recentActionLogs()
    {
        try {
            return CharlaTrackingActionLog::with('user')
                ->latest()
                ->limit(8)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
