<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\Request;

class StopDashboardController extends Controller
{
    public function index(Request $request)
    {
        $drive = new GoogleDriveService();

        if (!$drive->isConfigured()) {
            return view('stop-dashboard.index', [
                'error' => 'Google Drive no está configurado. Verifique las credenciales y el ID de carpeta en el archivo .env',
            ]);
        }

        $fileInfo = $drive->getLatestFile();

        if (!$fileInfo) {
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
            'fecha_desde'        => $request->input('fecha_desde', $isClean ? now()->startOfMonth()->format('Y-m-d') : null),
            'fecha_hasta'        => $request->input('fecha_hasta', $isClean ? now()->endOfMonth()->format('Y-m-d') : null),
            'mes'                => $request->input('mes'),
            'anio'               => $request->input('anio'),
        ]);

        // Analíticas filtradas (incluye filterOptions en la misma respuesta)
        $analytics = $drive->getFilteredAnalytics($filters);

        // Las opciones de filtro vienen incluidas en analytics para evitar doble lectura
        $filterOptions = $analytics['filterOptions'] ?? [];

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return view('stop-dashboard.index', [
                'error' => empty($filters)
                    ? 'No se pudieron obtener datos del archivo. Verifique que la carpeta esté compartida con la cuenta de servicio.'
                    : 'No se encontraron datos con los filtros seleccionados.',
                'fileInfo'      => $fileInfo,
                'filters'       => $filters,
                'filterOptions' => $filterOptions,
            ]);
        }

        // Checklist (sin filtros, datos globales)
        $checklist = $drive->getChecklistAnalytics();

        return view('stop-dashboard.index', compact(
            'fileInfo', 'analytics', 'checklist', 'filters', 'filterOptions'
        ));
    }

    public function sync()
    {
        $drive = new GoogleDriveService();
        $drive->clearCache();

        return back()->with('success', 'Cache limpiado. Los datos se recargarán desde Google Drive.');
    }

    /**
     * API endpoint para obtener datos filtrados en JSON.
     */
    public function apiData(Request $request)
    {
        $drive = new GoogleDriveService();

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

        $analytics = $drive->getFilteredAnalytics($filters);

        if (!$analytics) {
            return response()->json(['error' => 'No se pudieron obtener datos'], 500);
        }

        $checklist = $drive->getChecklistAnalytics();

        return response()->json([
            'analytics' => $analytics,
            'checklist' => $checklist,
        ]);
    }

    /**
     * Preview del reporte email en el navegador.
     */
    public function reportePreview(Request $request)
    {
        $data = \App\Console\Commands\StopWeeklyReport::buildReportData(
            $request->input('mes'),
            $request->input('anio'),
        );

        return new \App\Mail\StopReporteMail(
            analytics: $data['analytics'],
            periodo: $data['periodo'] ?? now()->format('d/m/Y'),
            mesLabel: $data['mesLabel'] ?? null,
        );
    }
}
