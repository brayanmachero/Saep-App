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

        // Filtros activos — por defecto mes en curso + empresa SAEP
        $isClean = !$request->hasAny(['empresa_observador','empresa_observado','tipo_observacion','centro','clasificacion','fecha_desde','fecha_hasta','mes','anio','all']);
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
     * Enviar reporte ahora a todos los destinatarios configurados,
     * usando los filtros activos del dashboard.
     */
    public function sendReportNow(Request $request)
    {
        $frecuencia = ucfirst($request->input('frecuencia', 'semanal'));
        $esMensual  = strtolower($frecuencia) === 'mensual';

        // Leer destinatarios desde configuración
        $configKey = $esMensual ? 'stop_report_mensual_destinatarios' : 'stop_report_destinatarios';
        $rawDestinatarios = \App\Models\Configuracion::get($configKey, '');
        $destinatarios = collect(preg_split('/[;,]+/', $rawDestinatarios))
            ->map(fn($e) => trim($e))
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($destinatarios->isEmpty()) {
            return back()->with('error', "No hay destinatarios configurados en «{$configKey}». Configure los emails en Ajustes.");
        }

        // Construir filtros desde el request (los mismos del dashboard)
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

        // Obtener analíticas con los filtros activos
        $sql = new StopAnalyticsService();
        $useSql = $sql->hasSyncedData();

        if ($useSql) {
            $analytics = $sql->getFilteredAnalytics($filters);
        } else {
            $drive = new GoogleDriveService();
            $analytics = $drive->getFilteredAnalytics($filters);
        }

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return back()->with('error', 'No hay datos con los filtros seleccionados para generar el reporte.');
        }

        // Comparativa
        $comparison = $useSql
            ? $sql->buildComparison($filters)
            : StopWeeklyReport::buildComparison(new GoogleDriveService(), $filters);

        // Detalle evaluación
        $evalDetail = $useSql
            ? ($sql->getEvaluationDetail($filters) ?? [])
            : ((new GoogleDriveService())->getEvaluationDetail($filters) ?? []);

        // Construir etiqueta de período
        $periodo = $this->buildPeriodoLabel($filters);

        $mailable = new StopReporteMail(
            analytics: $analytics,
            periodo: $periodo,
            mesLabel: $filters['mes'] ?? null,
            frecuencia: $frecuencia,
            comparison: $comparison,
            evalDetail: $evalDetail,
        );

        try {
            Mail::to($destinatarios->first())
                ->cc($destinatarios->slice(1)->values()->all())
                ->send($mailable);

            $count = $destinatarios->count();
            return back()->with('success', "Reporte {$frecuencia} enviado a {$count} destinatario(s) con los filtros activos del dashboard.");
        } catch (\Exception $e) {
            return back()->with('error', "Error al enviar reporte: {$e->getMessage()}");
        }
    }

    /**
     * Construir etiqueta de período legible a partir de los filtros.
     */
    private function buildPeriodoLabel(array $filters): string
    {
        if (!empty($filters['fecha_desde']) && !empty($filters['fecha_hasta'])) {
            return date('d/m/Y', strtotime($filters['fecha_desde'])) . ' — ' . date('d/m/Y', strtotime($filters['fecha_hasta']));
        }
        if (!empty($filters['mes'])) {
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $parts = explode('-', $filters['mes']);
            return ($meses[$parts[1] ?? ''] ?? '') . ' ' . ($parts[0] ?? '');
        }
        if (!empty($filters['anio'])) {
            return 'Año ' . $filters['anio'];
        }
        return now()->format('d/m/Y');
    }

    /**
     * Build year-over-year comparison using SQL data.
     */
}
