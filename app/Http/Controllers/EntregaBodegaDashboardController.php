<?php

namespace App\Http\Controllers;

use App\Jobs\DashboardSyncJob;
use App\Models\EntregaBodega;
use App\Services\EntregaBodegaAnalyticsService;
use App\Services\EntregaBodegaExcelExport;
use App\Services\EntregaBodegaSyncService;
use App\Services\KizeoService;
use Illuminate\Http\Request;

class EntregaBodegaDashboardController extends Controller
{
    private const LEGACY_KIZEO_FORM_ID = '947762';

    public function __construct(
        private readonly EntregaBodegaAnalyticsService $analytics,
        private readonly KizeoService $kizeo,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request);

        return view('entregas-bodega-dashboard.index', [
            'analytics' => $this->analytics->getFilteredAnalytics($filters),
            'filters' => $filters,
            'syncInfo' => $this->analytics->getSyncInfo(),
            'hasData' => $this->analytics->hasSyncedData(),
            'sourceForms' => EntregaBodegaSyncService::currentFormNames(),
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
            return back()->with('error', 'No fue posible iniciar la sincronización de entregas de bodega: '.$exception->getMessage());
        }

        if (! $queued) {
            return back()->with('error', 'Ya hay una sincronización de entregas de bodega en curso. Espera a que termine antes de iniciar otra.');
        }

        return back()->with('success', 'Sincronización iniciada en segundo plano. Los indicadores se actualizarán al terminar.');
    }

    public function downloadExcel(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $records = $this->analytics->getFilteredRecords($filters);

        if ($records->isEmpty()) {
            return back()->with('error', 'No hay entregas con los filtros seleccionados para exportar.');
        }

        $path = (new EntregaBodegaExcelExport)->generate(
            $this->analytics->getFilteredAnalytics($filters),
            $records,
            $filters,
        );

        return response()
            ->download($path, 'entregas_bodega_'.now()->format('Ymd_His').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function viewDocument(EntregaBodega $entrega)
    {
        try {
            $pdf = $this->kizeo->downloadPdf($entrega->kizeo_form_id ?: self::LEGACY_KIZEO_FORM_ID, $entrega->kizeo_data_id);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->documentUnavailableResponse(
                'Kizeo no pudo recuperar el PDF de esta entrega. Puede ser un registro local, una entrega antigua o un documento que aun no se ha generado.',
            );
        }

        if (! $pdf) {
            return $this->documentUnavailableResponse(
                'Esta entrega no tiene un PDF disponible en Kizeo. Puedes revisar los items completos desde el detalle de la entrega.',
            );
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="entrega-bodega-'.($entrega->kizeo_record_number ?: $entrega->kizeo_data_id).'.pdf"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function documentUnavailableResponse(string $message)
    {
        return response()->view('entregas-bodega-dashboard.document-unavailable', [
            'message' => $message,
        ], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
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
