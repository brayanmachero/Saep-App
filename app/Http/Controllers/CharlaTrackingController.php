<?php

namespace App\Http\Controllers;

use App\Console\Commands\KizeoCharlaWeeklyReport;
use App\Mail\CharlaTrackingReporteMail;
use App\Models\KizeoCharlaTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CharlaTrackingController extends Controller
{
    public function index(Request $request)
    {
        // Filtros — por defecto: primer día del mes en curso al día de hoy
        $desde  = $request->input('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta  = $request->input('hasta', now()->format('Y-m-d'));
        $estado = $request->input('estado', 'todos');
        $buscar = $request->input('buscar');

        $desdeCarbon = Carbon::parse($desde)->startOfDay();
        $hastaCarbon = Carbon::parse($hasta)->endOfDay();

        // === KPIs ===
        $baseQuery    = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon);
        $total        = (clone $baseQuery)->count();
        $completadas  = (clone $baseQuery)->completados()->count();
        $transferidos = (clone $baseQuery)->transferidos()->count();
        $pendientes   = (clone $baseQuery)->pendientes()->count();
        $tasa         = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        // Promedio días pendientes
        $promDias = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw('AVG(DATEDIFF(NOW(), COALESCE(fecha_asignacion, fecha_creacion))) as prom')
            ->value('prom');
        $promDias = $promDias ? round($promDias, 1) : 0;

        // === DATOS PARA GRÁFICOS ===

        // 1. Tendencia semanal
        $tendencia = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
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
        $distribucion = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("estatus_kizeo, COUNT(*) as cantidad")
            ->groupBy('estatus_kizeo')
            ->pluck('cantidad', 'estatus_kizeo')
            ->toArray();

        // 3. Top asignadores (quién crea/asigna más charlas — incluye directas y transferidas)
        $topAsignadores = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("asignado_por as usuario,
                         COUNT(*) as total_asignadas,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('asignado_por')
            ->orderByDesc('total_asignadas')
            ->limit(10)
            ->get();

        // 4. Cumplimiento por destinatario
        $porDestinatario = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
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
        $porUsuario = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("asignado_por as usuario,
                         COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('asignado_por')
            ->orderByRaw("SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) DESC")
            ->limit(15)
            ->get();

        // 6. Distribución por lugar/CD
        $porLugar = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
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
        $topPendientes = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("COALESCE(asignado_a, asignado_por) as responsable,
                         COUNT(*) as cantidad,
                         MIN(COALESCE(fecha_asignacion, fecha_creacion)) as mas_antigua,
                         MAX(DATEDIFF(NOW(), COALESCE(fecha_asignacion, fecha_creacion))) as dias_max")
            ->groupBy('responsable')
            ->orderByDesc('dias_max')
            ->limit(10)
            ->get();

        // 8. Tabla detalle filtrable
        $queryDetalle = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon);

        if ($estado && $estado !== 'todos') {
            if ($estado === 'pendiente') {
                $queryDetalle->pendientes();
            } elseif ($estado === 'completado') {
                $queryDetalle->completados();
            } elseif ($estado === 'transferido') {
                $queryDetalle->transferidos();
            }
        }

        if ($buscar) {
            $queryDetalle->where(function ($q) use ($buscar) {
                $q->where('asignado_por', 'like', "%{$buscar}%")
                  ->orWhere('asignado_a', 'like', "%{$buscar}%")
                  ->orWhere('titulo_actividad', 'like', "%{$buscar}%")
                  ->orWhere('lugar', 'like', "%{$buscar}%");
            });
        }

        $registrosList = $queryDetalle
            ->orderByDesc('fecha_creacion')
            ->paginate(20)
            ->withQueryString();

        // Última sincronización real (guardada en caché por el comando de sync)
        $ultimaSync = Cache::get('charla_tracking_last_sync');

        return view('charla-tracking.index', compact(
            'desde', 'hasta', 'estado', 'buscar',
            'total', 'completadas', 'pendientes', 'transferidos', 'tasa', 'promDias',
            'porUsuario', 'tendencia', 'distribucion',
            'topAsignadores', 'porDestinatario', 'porLugar',
            'registrosList', 'topPendientes', 'ultimaSync'
        ));
    }

    public function sync()
    {
        Artisan::call('kizeo:sync-charla-tracking', ['--months' => 6]);
        $output = Artisan::output();

        return back()->with('success', 'Sincronización completada. ' . trim($output));
    }

    public function emailPreview()
    {
        $data = KizeoCharlaWeeklyReport::buildReportData();

        $mailable = new CharlaTrackingReporteMail(
            $data['stats'],
            $data['pendientesPorUsuario'],
            $data['resumenSemanal'],
            $data['topDestinatarios'],
            $data['periodo'],
        );

        return $mailable->render();
    }

    public function sendNow()
    {
        Artisan::call('kizeo:charla-weekly-report', ['--sync' => true]);
        $output = Artisan::output();

        return back()->with('success', 'Reporte enviado exitosamente. ' . trim($output));
    }
}
