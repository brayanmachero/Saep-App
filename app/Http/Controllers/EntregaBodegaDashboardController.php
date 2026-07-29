<?php

namespace App\Http\Controllers;

use App\Jobs\DashboardSyncJob;
use App\Services\EntregaBodegaAnalyticsService;
use Illuminate\Http\Request;

class EntregaBodegaDashboardController extends Controller
{
    public function __construct(private readonly EntregaBodegaAnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        return view('entregas-bodega-dashboard.index', [
            'analytics' => $this->analytics->getFilteredAnalytics($filters),
            'filters' => $filters,
            'syncInfo' => $this->analytics->getSyncInfo(),
            'hasData' => $this->analytics->hasSyncedData(),
        ]);
    }

    public function sync()
    {
        try {
            $queued = DashboardSyncJob::dispatchOnce(
                'entregas-bodega',
                'kizeo:sync-entregas-bodega',
                ['--force' => true],
            );
        } catch (\Throwable $exception) {
            return back()->with('error', 'No fue posible iniciar la sincronización de entregas de bodega: ' . $exception->getMessage());
        }

        if (! $queued) {
            return back()->with('error', 'Ya hay una sincronización de entregas de bodega en curso. Espera a que termine antes de iniciar otra.');
        }

        return back()->with('success', 'Sincronización iniciada en segundo plano. Los indicadores se actualizarán al terminar.');
    }

    private function filtersFromRequest(Request $request): array
    {
        $keys = ['centro', 'trabajador', 'articulo', 'talla', 'fecha_desde', 'fecha_hasta', 'todo'];
        $clean = ! $request->hasAny($keys);
        $filters = array_filter([
            'centro' => $request->input('centro'),
            'trabajador' => $request->input('trabajador'),
            'articulo' => $request->input('articulo'),
            'talla' => $request->input('talla'),
            'fecha_desde' => $request->input('fecha_desde', $clean ? now()->startOfMonth()->toDateString() : null),
            'fecha_hasta' => $request->input('fecha_hasta', $clean ? now()->endOfMonth()->toDateString() : null),
        ], fn ($value) => $value !== null && $value !== '');

        if ($request->boolean('todo')) {
            $filters['todo'] = '1';
        }

        return $filters;
    }
}
