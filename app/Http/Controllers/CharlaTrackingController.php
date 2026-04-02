<?php

namespace App\Http\Controllers;

use App\Models\KizeoCharlaTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class CharlaTrackingController extends Controller
{
    public function index(Request $request)
    {
        // Filtros
        $desde  = $request->input('desde', now()->subMonths(3)->format('Y-m-d'));
        $hasta  = $request->input('hasta', now()->format('Y-m-d'));
        $estado = $request->input('estado');
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

        // 3. Top asignadores (quién transfiere más charlas)
        $topAsignadores = KizeoCharlaTracking::enPeriodo($desdeCarbon, $hastaCarbon)
            ->whereNotNull('asignado_a')
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
                         SUM(CASE WHEN estado IN('pendiente','transferido') THEN 1 ELSE 0 END) as pendientes")
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

        $ultimaSync = KizeoCharlaTracking::max('updated_at');

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

    /**
     * DEBUG: Compare Kizeo API endpoints to find where transferred records live.
     * TEMPORARY — remove after fixing sync.
     */
    public function debug(\App\Services\KizeoService $kizeo)
    {
        $formId = config('services.kizeo.charla_form_id');
        $desde = now()->subMonths(6)->format('Y-m-d H:i:s');
        $results = [];

        // 1. data/advanced (current approach — wrong filter format)
        try {
            $advResp = $kizeo->rawPost("forms/{$formId}/data/advanced", [
                'filters' => [
                    ['type' => 'OR', 'col' => '_create_time', 'op' => 'ge', 'val' => $desde],
                ],
                'order' => [['col' => '_create_time', 'type' => 'desc']],
                'limit' => 10,
            ]);
            $advData = $advResp['data'] ?? [];
            $results['advanced_old_format'] = [
                'count' => count($advData),
                'sample' => array_map(fn($r) => [
                    '_id' => $r['_id'] ?? null,
                    '_answer_time' => $r['_answer_time'] ?? 'NOT SET',
                    '_direction' => $r['_direction'] ?? 'NOT SET',
                    '_recipient_id' => $r['_recipient_id'] ?? 'NOT SET',
                    '_recipient_name' => $r['_recipient_name'] ?? 'NOT SET',
                    '_history' => $r['_history'] ?? 'NOT SET',
                    '_origin_answer' => $r['_origin_answer'] ?? 'NOT SET',
                    '_pull_time' => $r['_pull_time'] ?? 'NOT SET',
                    '_user_name' => $r['_user_name'] ?? 'NOT SET',
                ], array_slice($advData, 0, 3)),
            ];
        } catch (\Exception $e) {
            $results['advanced_old_format'] = ['error' => $e->getMessage()];
        }

        // 2. data/advanced (correct filter format per Swagger)
        try {
            $advResp2 = $kizeo->rawPost("forms/{$formId}/data/advanced", [
                'filters' => [
                    [
                        'type' => 'simple',
                        'field' => '_create_time',
                        'operator' => '>=',
                        'val' => $desde,
                    ],
                ],
                'order' => [['col' => '_create_time', 'type' => 'desc']],
                'limit' => 10,
            ]);
            $advData2 = $advResp2['data'] ?? [];
            $results['advanced_correct_format'] = [
                'count' => count($advData2),
                'sample' => array_map(fn($r) => [
                    '_id' => $r['_id'] ?? null,
                    '_answer_time' => $r['_answer_time'] ?? 'NOT SET',
                    '_direction' => $r['_direction'] ?? 'NOT SET',
                    '_recipient_id' => $r['_recipient_id'] ?? 'NOT SET',
                    '_recipient_name' => $r['_recipient_name'] ?? 'NOT SET',
                    '_history' => $r['_history'] ?? 'NOT SET',
                    '_origin_answer' => $r['_origin_answer'] ?? 'NOT SET',
                    '_pull_time' => $r['_pull_time'] ?? 'NOT SET',
                    '_user_name' => $r['_user_name'] ?? 'NOT SET',
                ], array_slice($advData2, 0, 3)),
            ];
        } catch (\Exception $e) {
            $results['advanced_correct_format'] = ['error' => $e->getMessage()];
        }

        // 3. data/unread with a test action
        try {
            $unreadResp = $kizeo->rawGet("forms/{$formId}/data/unread/saep_debug_test/1000?format=basic");
            $unreadData = $unreadResp['data'] ?? $unreadResp;
            if (!is_array($unreadData)) $unreadData = [];

            // Count statuses
            $statuses = ['has_answer' => 0, 'no_answer' => 0, 'has_direction' => 0, 'has_recipient' => 0, 'has_transfer_history' => 0];
            foreach ($unreadData as $r) {
                if (!empty(trim($r['_answer_time'] ?? ''))) $statuses['has_answer']++;
                else $statuses['no_answer']++;
                if (!empty($r['_direction'] ?? '')) $statuses['has_direction']++;
                if (!empty($r['_recipient_id'] ?? '')) $statuses['has_recipient']++;
                if (str_contains($r['_history'] ?? '', 'Transferido')) $statuses['has_transfer_history']++;
            }

            // Find a transferred sample
            $transferSamples = array_values(array_filter($unreadData, fn($r) =>
                empty(trim($r['_answer_time'] ?? '')) || !empty($r['_recipient_id'] ?? '')
            ));

            $results['unread_saep_debug'] = [
                'count' => count($unreadData),
                'status_breakdown' => $statuses,
                'sample_all' => array_map(fn($r) => [
                    '_id' => $r['_id'] ?? null,
                    '_answer_time' => $r['_answer_time'] ?? 'NOT SET',
                    '_direction' => $r['_direction'] ?? 'NOT SET',
                    '_recipient_id' => $r['_recipient_id'] ?? 'NOT SET',
                    '_recipient_name' => $r['_recipient_name'] ?? 'NOT SET',
                    '_history' => $r['_history'] ?? 'NOT SET',
                    '_origin_answer' => $r['_origin_answer'] ?? 'NOT SET',
                    '_pull_time' => $r['_pull_time'] ?? 'NOT SET',
                    '_user_name' => $r['_user_name'] ?? 'NOT SET',
                ], array_slice($unreadData, 0, 2)),
                'sample_transfers' => array_map(fn($r) => [
                    '_id' => $r['_id'] ?? null,
                    '_answer_time' => $r['_answer_time'] ?? 'NOT SET',
                    '_direction' => $r['_direction'] ?? 'NOT SET',
                    '_recipient_id' => $r['_recipient_id'] ?? 'NOT SET',
                    '_recipient_name' => $r['_recipient_name'] ?? 'NOT SET',
                    '_history' => $r['_history'] ?? 'NOT SET',
                    '_origin_answer' => $r['_origin_answer'] ?? 'NOT SET',
                    '_pull_time' => $r['_pull_time'] ?? 'NOT SET',
                    '_user_name' => $r['_user_name'] ?? 'NOT SET',
                ], array_slice($transferSamples, 0, 3)),
            ];
        } catch (\Exception $e) {
            $results['unread_saep_debug'] = ['error' => $e->getMessage()];
        }

        // 4. push/inbox
        try {
            $inboxResp = $kizeo->rawGet("forms/push/inbox");
            $inboxData = $inboxResp['data'] ?? $inboxResp;
            if (!is_array($inboxData)) $inboxData = [];

            // Filter for our form
            $ourFormPushes = array_values(array_filter($inboxData, fn($r) =>
                ($r['_form_id'] ?? $r['form_id'] ?? '') == $formId
            ));

            $results['push_inbox'] = [
                'total_all_forms' => count($inboxData),
                'our_form_count' => count($ourFormPushes),
                'sample' => array_map(fn($r) => array_intersect_key($r, array_flip([
                    '_id', 'id', '_form_id', 'form_id', '_answer_time', '_direction',
                    '_recipient_id', '_recipient_name', '_history', '_origin_answer',
                    '_user_name', '_pull_time', 'name',
                ])), array_slice($ourFormPushes ?: $inboxData, 0, 3)),
            ];
        } catch (\Exception $e) {
            $results['push_inbox'] = ['error' => $e->getMessage()];
        }

        // 5. All field keys from first advanced record
        try {
            $advAll = $kizeo->rawPost("forms/{$formId}/data/advanced", [
                'limit' => 1,
            ]);
            $firstRec = ($advAll['data'] ?? [])[0] ?? [];
            $results['available_fields'] = array_keys($firstRec);
        } catch (\Exception $e) {
            $results['available_fields'] = ['error' => $e->getMessage()];
        }

        return response()->json($results, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
