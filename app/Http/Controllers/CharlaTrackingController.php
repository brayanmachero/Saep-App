<?php

namespace App\Http\Controllers;

use App\Models\KizeoCharlaTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CharlaTrackingController extends Controller
{
    /**
     * Dashboard de seguimiento de charlas.
     */
    public function index(Request $request)
    {
        // Filtros
        $desde = $request->input('desde', now()->subMonths(3)->format('Y-m-d'));
        $hasta = $request->input('hasta', now()->format('Y-m-d'));
        $estado = $request->input('estado');
        $buscar = $request->input('buscar');

        $desdeCarbon = Carbon::parse($desde)->startOfDay();
        $hastaCarbon = Carbon::parse($hasta)->endOfDay();

        // KPIs
        $baseQuery = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon);
        $total       = (clone $baseQuery)->count();
        $completadas = (clone $baseQuery)->completados()->count();
        $pendientes  = (clone $baseQuery)->pendientes()->count();
        $tasa        = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;

        // Promedio días pendientes
        $promDias = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw('AVG(DATEDIFF(NOW(), fecha_creacion)) as prom')
            ->value('prom');
        $promDias = $promDias ? round($promDias, 1) : 0;

        // === DATOS PARA GRÁFICOS ===

        // 1. Cumplimiento por usuario (horizontal bar)
        $porUsuario = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("asignado_por as usuario,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) as pendientes")
            ->groupBy('asignado_por')
            ->orderByRaw("SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) DESC")
            ->limit(15)
            ->get();

        // 2. Tendencia semanal (line chart)
        $tendencia = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("anio, semana,
                         COUNT(*) as total,
                         SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) as completadas,
                         SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) as pendientes")
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

        // 3. Distribución estado (doughnut)
        $distribucion = [
            'completadas' => $completadas,
            'pendientes'  => $pendientes,
        ];

        // 4. Tabla de pendientes (detalle)
        $queryPendientes = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desdeCarbon, $hastaCarbon);

        if ($buscar) {
            $queryPendientes->where(function ($q) use ($buscar) {
                $q->where('asignado_por', 'like', "%{$buscar}%")
                  ->orWhere('asignado_a', 'like', "%{$buscar}%")
                  ->orWhere('kizeo_data_id', 'like', "%{$buscar}%");
            });
        }

        $pendientesList = $queryPendientes
            ->orderBy('fecha_creacion')
            ->paginate(20)
            ->withQueryString();

        // 5. Top pendientes: usuarios con más tiempo sin completar
        $topPendientes = KizeoCharlaTracking::pendientes()
            ->enPeriodo($desdeCarbon, $hastaCarbon)
            ->selectRaw("asignado_por as usuario, COUNT(*) as cantidad, MIN(fecha_creacion) as mas_antigua, MAX(DATEDIFF(NOW(), fecha_creacion)) as dias_max")
            ->groupBy('asignado_por')
            ->orderByDesc('dias_max')
            ->limit(10)
            ->get();

        // Última sincronización
        $ultimaSync = KizeoCharlaTracking::max('updated_at');

        return view('charla-tracking.index', compact(
            'desde', 'hasta', 'estado', 'buscar',
            'total', 'completadas', 'pendientes', 'tasa', 'promDias',
            'porUsuario', 'tendencia', 'distribucion',
            'pendientesList', 'topPendientes', 'ultimaSync'
        ));
    }
}
