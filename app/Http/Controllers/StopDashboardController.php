<?php

namespace App\Http\Controllers;

use App\Console\Commands\StopWeeklyReport;
use App\Mail\StopReporteMail;
use App\Models\StopActionLog;
use App\Services\GoogleDriveService;
use App\Services\StopAnalyticsService;
use App\Services\StopExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class StopDashboardController extends Controller
{
    public function index(Request $request)
    {
        $sql = new StopAnalyticsService();
        $useSql = $sql->hasSyncedData();
        $stopActionLogs = $this->recentStopActionLogs();

        // Si no hay data en SQL, necesitamos Google Drive como fuente
        $drive = new GoogleDriveService();

        if (!$useSql && !$drive->isConfigured()) {
            return view('stop-dashboard.index', [
                'error' => 'Google Drive no está configurado. Verifique las credenciales y el ID de carpeta en el archivo .env',
                'stopActionLogs' => $stopActionLogs,
            ]);
        }

        $fileInfo = $drive->isConfigured() ? $drive->getLatestFile() : null;
        $syncInfo = $sql->getSyncInfo();

        if (!$useSql && !$fileInfo) {
            return view('stop-dashboard.index', [
                'error' => 'No se encontraron archivos en la carpeta de Google Drive.',
                'stopActionLogs' => $stopActionLogs,
            ]);
        }

        // Filtros activos — por defecto mes en curso + empresa SAEP
        $isClean = !$request->hasAny(['empresa_observador','empresa_observado','tipo_observacion','centro','clasificacion','fecha_desde','fecha_hasta','mes','anio','trabajador','all']);
        $defaultEmpresa = \App\Models\Configuracion::get('stop_report_empresa', 'SAEP');
        $filters = array_filter([
            'empresa_observador' => $request->input('empresa_observador'),
            'empresa_observado'  => $request->input('empresa_observado', $isClean ? $defaultEmpresa : null),
            'tipo_observacion'   => $request->input('tipo_observacion'),
            'centro'             => $request->input('centro'),
            'clasificacion'      => $request->input('clasificacion'),
            'fecha_desde'        => $request->input('fecha_desde', $isClean && !$request->input('mes') && !$request->input('anio') ? now()->startOfMonth()->format('Y-m-d') : null),
            'fecha_hasta'        => $request->input('fecha_hasta', $isClean && !$request->input('mes') && !$request->input('anio') ? now()->endOfMonth()->format('Y-m-d') : null),
            'mes'                => $request->input('mes'),
            'anio'               => $request->input('anio'),
            'trabajador'         => $request->input('trabajador'),
        ]);

        // Obtener analíticas — SQL si hay datos sincronizados, Google Sheets si no
        if ($useSql) {
            $analytics = $sql->getFilteredAnalytics($filters);
            $filterOptions = $analytics['filterOptions'] ?? [];
        } else {
            $analytics = $drive->getFilteredAnalytics($filters);
            $filterOptions = $analytics['filterOptions'] ?? [];
        }

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return view('stop-dashboard.index', [
                'error' => empty($filters)
                    ? 'No se pudieron obtener datos del archivo. Verifique que la carpeta esté compartida con la cuenta de servicio.'
                    : 'No se encontraron datos con los filtros seleccionados.',
                'fileInfo'      => $fileInfo,
                'syncInfo'      => $syncInfo,
                'filters'       => $filters,
                'filterOptions' => $filterOptions,
                'stopActionLogs' => $stopActionLogs,
            ]);
        }

        // Checklist
        $checklist = $useSql ? $sql->getChecklistAnalytics($filters) : $drive->getChecklistAnalytics();

        // Comparativa año anterior + acumulado YTD
        if ($useSql) {
            $comparison = $sql->buildComparison($filters);
        } else {
            $comparison = StopWeeklyReport::buildComparison($drive, $filters);
        }

        // Detalle de evaluación negativas
        $evalDetail = $useSql
            ? ($sql->getEvaluationDetail($filters) ?? [])
            : ($drive->getEvaluationDetail($filters) ?? []);

        return view('stop-dashboard.index', compact(
            'fileInfo', 'syncInfo', 'analytics', 'checklist', 'filters', 'filterOptions', 'comparison', 'evalDetail', 'stopActionLogs'
        ));
    }

    public function sync(Request $request)
    {
        $drive = new GoogleDriveService();

        // Ejecutar sincronización a MySQL
        try {
            Artisan::call('stop:sync-sheets', ['--force' => true]);
            $output = Artisan::output();

            // También limpiar caché de Google Drive
            $drive->clearCache();

            $this->recordStopAction($request, 'sync', 'success', 'Sincronización manual de datos STOP completada.', [], [
                'output' => trim($output),
            ]);

            return back()->with('success', 'Datos sincronizados exitosamente desde Google Sheets. ' . trim($output));
        } catch (\Throwable $e) {
            $this->recordStopAction($request, 'sync', 'failed', 'Error durante sincronización manual de datos STOP.', [], [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error durante sincronización: ' . $e->getMessage());
        }
    }

    /**
     * API endpoint para obtener datos filtrados en JSON.
     */
    public function apiData(Request $request)
    {
        $sql = new StopAnalyticsService();
        $useSql = $sql->hasSyncedData();

        $filters = array_filter([
            'empresa_observador' => $request->input('empresa_observador'),
            'empresa_observado'  => $request->input('empresa_observado'),
            'tipo_observacion'   => $request->input('tipo_observacion'),
            'centro'             => $request->input('centro'),
            'clasificacion'      => $request->input('clasificacion'),
            'fecha_desde'        => $request->input('fecha_desde'),
            'fecha_hasta'        => $request->input('fecha_hasta'),
            'mes'                => $request->input('mes'),
            'anio'               => $request->input('anio'),
            'trabajador'         => $request->input('trabajador'),
        ]);

        if ($useSql) {
            $analytics = $sql->getFilteredAnalytics($filters);
            $checklist = $sql->getChecklistAnalytics($filters);
        } else {
            $drive = new GoogleDriveService();
            $analytics = $drive->getFilteredAnalytics($filters);
            $checklist = $drive->getChecklistAnalytics();
        }

        if (!$analytics) {
            return response()->json(['error' => 'No se pudieron obtener datos'], 500);
        }

        return response()->json([
            'analytics' => $analytics,
            'checklist' => $checklist,
        ]);
    }

    /**
     * Preview del reporte email en el navegador con toolbar de envío de prueba.
     */
    public function reportePreview(Request $request)
    {
        $filters = $this->reportFiltersFromRequest($request);
        $freq = $request->input('frecuencia', 'Semanal');

        $data = StopWeeklyReport::buildReportDataFromFilters($filters);

        $mailable = new StopReporteMail(
            analytics: $data['analytics'],
            periodo: $data['periodo'] ?? now()->format('d/m/Y'),
            mesLabel: $data['mesLabel'] ?? null,
            frecuencia: $freq,
            comparison: $data['comparison'] ?? [],
            evalDetail: $data['evalDetail'] ?? [],
        );

        $emailHtml = $mailable->render();

        return view('stop-dashboard.reporte-preview', [
            'emailHtml'  => $emailHtml,
            'filters'    => $data['filters'] ?? $filters,
            'frecuencia' => $freq,
            'periodo'    => $data['periodo'] ?? now()->format('d/m/Y'),
            'totalRows'  => $data['analytics']['totalRows'] ?? 0,
            'success'    => session('success'),
            'error'      => session('error'),
        ]);
    }

    public function downloadExcelReport(Request $request)
    {
        $filters = $this->reportFiltersFromRequest($request);
        $frecuencia = $request->input('frecuencia', 'Semanal');
        $path = null;

        $data = StopWeeklyReport::buildReportDataFromFilters($filters);
        $analytics = $data['analytics'] ?? ['totalRows' => 0];

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            $this->recordStopAction($request, 'report_excel_download', 'skipped', 'Descarga Excel STOP omitida por falta de datos.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'total_rows' => 0,
            ]);

            return back()->with('error', 'No hay datos con los filtros seleccionados para descargar el Excel.');
        }

        try {
            $path = (new StopExcelExport())->generate(
                $analytics,
                $data['periodo'] ?? now()->format('d/m/Y'),
                $frecuencia,
                $data['comparison'] ?? [],
                $data['evalDetail'] ?? [],
            );

            $filename = $this->excelFilename($data['periodo'] ?? null);

            $this->recordStopAction($request, 'report_excel_download', 'success', 'Reporte STOP Excel descargado con filtros activos.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $data['periodo'] ?? null,
                'filename' => $filename,
                'total_rows' => $analytics['totalRows'] ?? 0,
            ]);

            return response()
                ->download($path, $filename)
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if ($path && file_exists($path)) {
                @unlink($path);
            }

            $this->recordStopAction($request, 'report_excel_download', 'failed', 'Error al descargar reporte STOP Excel.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $data['periodo'] ?? null,
                'total_rows' => $analytics['totalRows'] ?? 0,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Error al generar Excel STOP: {$e->getMessage()}");
        }
    }

    /**
     * Enviar reporte de prueba a un email específico.
     */
    public function sendTestReport(Request $request)
    {
        $request->validate([
            'email'      => 'required|email',
            'frecuencia' => 'in:Semanal,Mensual',
        ]);

        $filters = $this->reportFiltersFromRequest($request);
        $freq = $request->input('frecuencia', 'Semanal');
        $data = StopWeeklyReport::buildReportDataFromFilters($filters);

        if (($data['analytics']['totalRows'] ?? 0) === 0) {
            $this->recordStopAction($request, 'report_test_send', 'skipped', 'Reporte STOP de prueba omitido por falta de datos.', $data['filters'] ?? $filters, [
                'recipient' => $request->input('email'),
                'frecuencia' => $freq,
                'total_rows' => 0,
            ]);

            return back()->with('error', 'No hay datos para el período seleccionado.');
        }

        $mailable = new StopReporteMail(
            analytics: $data['analytics'],
            periodo: $data['periodo'] ?? now()->format('d/m/Y'),
            mesLabel: $data['mesLabel'] ?? null,
            frecuencia: $freq,
            comparison: $data['comparison'] ?? [],
            evalDetail: $data['evalDetail'] ?? [],
        );

        try {
            Mail::to($request->input('email'))->send($mailable);

            $this->recordStopAction($request, 'report_test_send', 'success', 'Reporte STOP de prueba enviado.', $data['filters'] ?? $filters, [
                'recipient' => $request->input('email'),
                'frecuencia' => $freq,
                'periodo' => $data['periodo'] ?? null,
                'total_rows' => $data['analytics']['totalRows'] ?? 0,
            ]);

            return back()->with('success', "Reporte de prueba enviado a {$request->input('email')}");
        } catch (\Throwable $e) {
            $this->recordStopAction($request, 'report_test_send', 'failed', 'Error al enviar reporte STOP de prueba.', $data['filters'] ?? $filters, [
                'recipient' => $request->input('email'),
                'frecuencia' => $freq,
                'periodo' => $data['periodo'] ?? null,
                'total_rows' => $data['analytics']['totalRows'] ?? 0,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Error al enviar: {$e->getMessage()}");
        }
    }

    /**
     * Enviar reporte ahora a todos los destinatarios configurados,
     * usando los filtros activos del dashboard.
     */
    public function sendReportNow(Request $request)
    {
        $frecuencia = ucfirst($request->input('frecuencia', 'semanal'));
        $esMensual  = strtolower($frecuencia) === 'mensual';
        $filters = $this->reportFiltersFromRequest($request);

        // Leer destinatarios desde configuración
        $configKey = $esMensual ? 'stop_report_mensual_destinatarios' : 'stop_report_destinatarios';
        $rawDestinatarios = \App\Models\Configuracion::get($configKey, '');
        $destinatarios = collect(preg_split('/[;,]+/', $rawDestinatarios))
            ->map(fn($e) => trim($e))
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($destinatarios->isEmpty()) {
            $this->recordStopAction($request, 'report_send_now', 'skipped', 'Reporte STOP manual omitido por falta de destinatarios configurados.', $filters, [
                'frecuencia' => $frecuencia,
                'config_key' => $configKey,
            ]);

            return back()->with('error', "No hay destinatarios configurados en «{$configKey}». Configure los emails en Ajustes.");
        }

        $data = StopWeeklyReport::buildReportDataFromFilters($filters);
        $analytics = $data['analytics'] ?? ['totalRows' => 0];

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            $this->recordStopAction($request, 'report_send_now', 'skipped', 'Reporte STOP manual omitido por falta de datos.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'recipients_count' => $destinatarios->count(),
                'total_rows' => 0,
            ]);

            return back()->with('error', 'No hay datos con los filtros seleccionados para generar el reporte.');
        }

        $mailable = new StopReporteMail(
            analytics: $analytics,
            periodo: $data['periodo'] ?? now()->format('d/m/Y'),
            mesLabel: $data['mesLabel'] ?? null,
            frecuencia: $frecuencia,
            comparison: $data['comparison'] ?? [],
            evalDetail: $data['evalDetail'] ?? [],
        );

        try {
            Mail::to($destinatarios->first())
                ->cc($destinatarios->slice(1)->values()->all())
                ->send($mailable);

            $count = $destinatarios->count();

            $this->recordStopAction($request, 'report_send_now', 'success', 'Reporte STOP manual enviado con filtros activos.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $data['periodo'] ?? null,
                'recipients' => $destinatarios->all(),
                'recipients_count' => $count,
                'total_rows' => $analytics['totalRows'] ?? 0,
            ]);

            return back()->with('success', "Reporte {$frecuencia} enviado a {$count} destinatario(s) con los filtros activos del dashboard.");
        } catch (\Throwable $e) {
            $this->recordStopAction($request, 'report_send_now', 'failed', 'Error al enviar reporte STOP manual.', $data['filters'] ?? $filters, [
                'frecuencia' => $frecuencia,
                'periodo' => $data['periodo'] ?? null,
                'recipients' => $destinatarios->all(),
                'recipients_count' => $destinatarios->count(),
                'total_rows' => $analytics['totalRows'] ?? 0,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Error al enviar reporte: {$e->getMessage()}");
        }
    }

    private function reportFiltersFromRequest(Request $request): array
    {
        $filters = $request->only(StopWeeklyReport::reportFilterKeys());

        if ($request->boolean('all')) {
            $filters['all'] = '1';
        }

        return array_filter(
            $filters,
            fn ($value) => $value !== null && $value !== ''
        );
    }

    private function recordStopAction(
        Request $request,
        string $action,
        string $status,
        ?string $summary = null,
        array $filters = [],
        array $metadata = []
    ): void {
        $userAgent = $request->userAgent();

        StopActionLog::record([
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

    private function recentStopActionLogs()
    {
        try {
            return StopActionLog::with('user')
                ->latest()
                ->limit(8)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function excelFilename(?string $periodo): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $periodo ?: now()->format('Y-m-d'));
        $slug = trim((string) $slug, '-');

        return 'Reporte_STOP_CCU_' . ($slug ?: now()->format('Y-m-d')) . '.xlsx';
    }
}
