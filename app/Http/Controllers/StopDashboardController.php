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

        // Analíticas optimizadas: solo lee columnas A-S (metadata) — eficiente con 26K+ filas
        $analytics = $drive->getStopAnalytics();

        if (!$analytics || ($analytics['totalRows'] ?? 0) === 0) {
            return view('stop-dashboard.index', [
                'error' => 'No se pudieron obtener datos del archivo. Verifique que la carpeta esté compartida con la cuenta de servicio.',
                'fileInfo' => $fileInfo,
            ]);
        }

        // Analíticas de checklist (EPP, Reglas de Oro, etc.)
        $checklist = $drive->getChecklistAnalytics();

        return view('stop-dashboard.index', compact('fileInfo', 'analytics', 'checklist'));
    }

    public function sync()
    {
        $drive = new GoogleDriveService();
        $drive->clearCache();

        return back()->with('success', 'Cache limpiado. Los datos se recargarán desde Google Drive.');
    }

    /**
     * API endpoint para obtener datos en JSON (para gráficos dinámicos).
     */
    public function apiData()
    {
        $drive = new GoogleDriveService();
        $analytics = $drive->getStopAnalytics();

        if (!$analytics) {
            return response()->json(['error' => 'No se pudieron obtener datos'], 500);
        }

        $checklist = $drive->getChecklistAnalytics();

        return response()->json([
            'analytics' => $analytics,
            'checklist' => $checklist,
        ]);
    }
}
