<?php

namespace App\Http\Controllers;

use App\Console\Commands\StopWeeklyReport;
use App\Mail\StopReporteMail;
use App\Services\GoogleDriveService;
use App\Services\StopAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class StopDashboardController extends Controller
{
    public function index(Request $request)
    {
        $sql = new StopAnalyticsService();
        $useSql = $sql->hasSyncedData();

        // Si no hay data en SQL, necesitamos Google Drive como fuente
        $drive = new GoogleDriveService();

        if (!$useSql && !$drive->isConfigured()) {
            return view('stop-dashboard.index', [
                'error' => 'Google Drive no está configurado. Verifique las credenciales y el ID de carpeta en el archivo .env',
            ]);
        }

        $fileInfo = $drive->isConfigured() ? $drive->getLatestFile() : null;
        $syncInfo = $sql->getSyncInfo();

        if (!$useSql && !$fileInfo) {
            return view('stop-dashboard.index', [
                'error' => 'No se encontraron archivos en la carpeta de Google Drive.',
            ]);
        }

        // Filtros activos — por defecto mes en curso
        $isClean = !$request->hasAny(['empresa_observador','empresa_observado','tipo_observacion','centro','clasificacion','fecha_desde','fecha_hasta','mes','anio','all']);
        $filters = array_filter([
            'empresa_observador' => $request->input('empresa_observador'),
            'empresa_observado'  => $request->input('empresa_observado'),
            'tipo_observacion'   => $request->input('tipo_observacion'),
            'centro'             => $request->input('centro'),
            'clasificacion'      => $request->input('clasificacion'),
            'fecha_desde'        => $request->input('fecha_desde', $isClean && !$request->input('mes') && !$request->input('anio') ? now()->startOfMonth()->format('Y-m-d') : null),
            'fecha_hasta'        => $request->input('fecha_hasta', $isClean && !$request->input('mes') && !$request->input('anio') ? now()->endOfMonth()->format('Y-m-d') : null),
            'mes'                => $request->input('mes'),
            'anio'               => $request->input('anio'),
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
            ]);
        }

        // Checklist
        $checklist = $useSql ? $sql->getChecklistAnalytics() : $drive->getChecklistAnalytics();

        // Comparativa año anterior + acumulado YTD
        $empresa = $filters['empresa_observador'] ?? null;
        if ($useSql) {
            $comparison = $this->buildComparisonFromSql($sql, $filters, $empresa);
        } else {
            $comparison = StopWeeklyReport::buildComparison($drive, $filters, $empresa);
        }

        // Detalle de evaluación negativas
        $evalDetail = $useSql
            ? ($sql->getEvaluationDetail($filters) ?? [])
            : ($drive->getEvaluationDetail($filters) ?? []);

        return view('stop-dashboard.index', compact(
            'fileInfo', 'syncInfo', 'analytics', 'checklist', 'filters', 'filterOptions', 'comparison', 'evalDetail'
        ));
    }

    public function sync()
    {
        $drive = new GoogleDriveService();

        // Ejecutar sincronización a MySQL
        try {
            Artisan::call('stop:sync-sheets', ['--force' => true]);
            $output = Artisan::output();

            // También limpiar caché de Google Drive
            $drive->clearCache();

            return back()->with('success', 'Datos sincronizados exitosamente desde Google Sheets. ' . trim($output));
        } catch (\Exception $e) {
            return back()->with('success', 'Error durante sincronización: ' . $e->getMessage());
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
        ]);

        if ($useSql) {
            $analytics = $sql->getFilteredAnalytics($filters);
            $checklist = $sql->getChecklistAnalytics();
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
        $mes  = $request->input('mes');
        $anio = $request->input('anio');
        $freq = $request->input('frecuencia', 'Semanal');

        $data = \App\Console\Commands\StopWeeklyReport::buildReportData($mes, $anio);

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
            'mes'        => $mes,
            'anio'       => $anio,
            'frecuencia' => $freq,
            'periodo'    => $data['periodo'] ?? now()->format('d/m/Y'),
            'totalRows'  => $data['analytics']['totalRows'] ?? 0,
            'success'    => session('success'),
            'error'      => session('error'),
        ]);
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

        $data = \App\Console\Commands\StopWeeklyReport::buildReportData(
            $request->input('mes'),
            $request->input('anio'),
        );

        if (($data['analytics']['totalRows'] ?? 0) === 0) {
            return back()->with('error', 'No hay datos para el período seleccionado.');
        }

        $freq = $request->input('frecuencia', 'Semanal');

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

            return back()->with('success', "Reporte de prueba enviado a {$request->input('email')}");
        } catch (\Exception $e) {
            return back()->with('error', "Error al enviar: {$e->getMessage()}");
        }
    }

    /**
     * Enviar reporte semanal ahora a todos los destinatarios configurados.
     */
    public function sendReportNow(Request $request)
    {
        $frecuencia = $request->input('frecuencia', 'semanal');

        Artisan::call('stop:weekly-report', ['--frecuencia' => $frecuencia]);
        $output = Artisan::output();

        return back()->with('success', "Reporte {$frecuencia} enviado exitosamente. " . trim($output));
    }

    /**
     * Build year-over-year comparison using SQL data.
     */
    private function buildComparisonFromSql(StopAnalyticsService $sql, array $baseFilters, ?string $empresa): array
    {
        $currentYear  = (int) ($baseFilters['anio'] ?? now()->format('Y'));
        $prevYear     = $currentYear - 1;
        $currentMonth = $baseFilters['mes'] ?? now()->format('Y-m');
        $monthNum     = (int) substr($currentMonth, 5, 2);

        $empFilter = $empresa ? ['empresa_observador' => $empresa] : [];

        try {
            $ytdData  = $sql->getFilteredAnalytics(array_merge(['anio' => (string) $currentYear], $empFilter));
            $prevData = $sql->getFilteredAnalytics(array_merge(['anio' => (string) $prevYear], $empFilter));
        } catch (\Throwable $e) {
            return [];
        }

        $ytdClasif  = $ytdData['clasificacion'] ?? [];
        $prevClasif = $prevData['clasificacion'] ?? [];

        $prevByMonth    = $prevData['byMonth'] ?? [];
        $prevByMonthNeg = $prevData['byMonthNeg'] ?? [];
        $prevByMonthPos = $prevData['byMonthPos'] ?? [];

        $prevYearMonth = $prevYear . '-' . str_pad($monthNum, 2, '0', STR_PAD_LEFT);

        return [
            'ytd' => [
                'total'      => $ytdData['totalRows'] ?? 0,
                'pos'        => $ytdClasif['Positiva'] ?? $ytdClasif['positiva'] ?? 0,
                'neg'        => $ytdClasif['Negativa'] ?? $ytdClasif['negativa'] ?? 0,
                'topNeg'     => $ytdData['topNegTrabajadores'] ?? [],
                'negPorTipo' => $ytdData['negPorTipo'] ?? [],
                'byMonth'    => $ytdData['byMonth'] ?? [],
                'byMonthNeg' => $ytdData['byMonthNeg'] ?? [],
                'byMonthPos' => $ytdData['byMonthPos'] ?? [],
            ],
            'prevYear' => [
                'year'           => $prevYear,
                'total'          => $prevData['totalRows'] ?? 0,
                'pos'            => $prevClasif['Positiva'] ?? $prevClasif['positiva'] ?? 0,
                'neg'            => $prevClasif['Negativa'] ?? $prevClasif['negativa'] ?? 0,
                'sameMonthTotal' => $prevByMonth[$prevYearMonth] ?? 0,
                'sameMonthPos'   => $prevByMonthPos[$prevYearMonth] ?? 0,
                'sameMonthNeg'   => $prevByMonthNeg[$prevYearMonth] ?? 0,
                'ytdTotal'       => $prevData['totalRows'] ?? 0,
                'ytdPos'         => $prevClasif['Positiva'] ?? $prevClasif['positiva'] ?? 0,
                'ytdNeg'         => $prevClasif['Negativa'] ?? $prevClasif['negativa'] ?? 0,
                'byMonth'        => $prevByMonth,
                'byMonthNeg'     => $prevByMonthNeg,
                'byMonthPos'     => $prevByMonthPos,
            ],
        ];
    }
}
