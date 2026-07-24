<?php

namespace App\Http\Controllers;

use App\Mail\ObservacionConductaCcuReporteMail;
use App\Services\ObservacionConductaCcuAnalyticsService;
use App\Services\ObservacionConductaCcuExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class ObservacionConductaCcuDashboardController extends Controller
{
    public function __construct(private readonly ObservacionConductaCcuAnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filtersFromRequest($request, true);

        return view('observaciones-ccu-dashboard.index', [
            'analytics' => $this->analytics->getFilteredAnalytics($filters),
            'filters' => $filters,
            'syncInfo' => $this->analytics->getSyncInfo(),
            'hasData' => $this->analytics->hasSyncedData(),
        ]);
    }

    public function sync()
    {
        try {
            Artisan::call('kizeo:sync-observaciones-ccu', ['--force' => true]);

            return back()->with('success', trim(Artisan::output()) ?: 'Datos de Observaciones CCU sincronizados.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No fue posible sincronizar Observaciones CCU: ' . $e->getMessage());
        }
    }

    public function downloadExcel(Request $request)
    {
        $filters = $this->filtersFromRequest($request, false);
        $analytics = $this->analytics->getFilteredAnalytics($filters);

        if (($analytics['total'] ?? 0) === 0) {
            return back()->with('error', 'No hay registros con los filtros seleccionados para exportar.');
        }

        $path = (new ObservacionConductaCcuExcelExport())->generate(
            $analytics,
            $this->analytics->getFilteredRecords($filters),
            $filters,
        );

        return response()
            ->download($path, 'observaciones_ccu_' . now()->format('Ymd_His') . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function emailPreview(Request $request)
    {
        [$analytics, $records, $filters] = $this->reportData($request);

        if (($analytics['total'] ?? 0) === 0) {
            return redirect()
                ->route('pdr-ccu-dashboard.index', $filters)
                ->with('error', 'No hay registros con los filtros seleccionados para generar el correo.');
        }

        $mailable = $this->reportMailable(
            $analytics,
            $records,
            $filters,
            (string) ($request->user()?->name ?? 'Usuario SAEP'),
            false,
        );

        return view('observaciones-ccu-dashboard.reporte-preview', [
            'emailHtml' => $mailable->render(),
            'filters' => $filters,
        ]);
    }

    public function sendToCurrentUser(Request $request)
    {
        $user = $request->user();
        $email = trim((string) ($user?->email ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Tu usuario no tiene un correo válido para recibir el reporte.');
        }

        [$analytics, $records, $filters] = $this->reportData($request);

        if (($analytics['total'] ?? 0) === 0) {
            return back()->with('error', 'No hay registros con los filtros seleccionados para generar el correo.');
        }

        try {
            Mail::to($email)->send($this->reportMailable(
                $analytics,
                $records,
                $filters,
                (string) ($user->name ?? 'Usuario SAEP'),
            ));

            return back()->with('success', "Reporte enviado a {$email} con el Excel adjunto.");
        } catch (\Throwable $e) {
            return back()->with('error', 'No fue posible enviar el reporte: ' . $e->getMessage());
        }
    }

    private function filtersFromRequest(Request $request, bool $defaultCurrentMonth): array
    {
        $isClean = !$request->hasAny([
            'centro', 'clasificacion', 'observador_nombre', 'trabajador_nombre', 'tipo_observacion',
            'fecha_desde', 'fecha_hasta', 'todo',
        ]);

        $filters = array_filter([
            'centro' => $request->input('centro'),
            'clasificacion' => $request->input('clasificacion'),
            'observador_nombre' => $request->input('observador_nombre'),
            'trabajador_nombre' => $request->input('trabajador_nombre'),
            'tipo_observacion' => $request->input('tipo_observacion'),
            'fecha_desde' => $request->input('fecha_desde', $defaultCurrentMonth && $isClean ? now()->startOfMonth()->toDateString() : null),
            'fecha_hasta' => $request->input('fecha_hasta', $defaultCurrentMonth && $isClean ? now()->endOfMonth()->toDateString() : null),
        ], fn ($value) => $value !== null && $value !== '');

        if ($request->boolean('todo')) {
            $filters['todo'] = '1';
        }

        return $filters;
    }

    private function reportData(Request $request): array
    {
        $filters = $this->filtersFromRequest($request, true);
        $analytics = $this->analytics->getFilteredAnalytics($filters);

        return [
            $analytics,
            $this->analytics->getFilteredRecords($filters),
            $filters,
        ];
    }

    private function reportMailable(
        array $analytics,
        $records,
        array $filters,
        string $recipientName,
        bool $attachExcel = true,
    ): ObservacionConductaCcuReporteMail {
        return new ObservacionConductaCcuReporteMail(
            analytics: $analytics,
            records: $records,
            filters: $filters,
            dashboardUrl: route('pdr-ccu-dashboard.index', $filters),
            recipientName: $recipientName,
            attachExcel: $attachExcel,
        );
    }
}
